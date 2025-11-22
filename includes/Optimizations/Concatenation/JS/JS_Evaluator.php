<?php
/**
 * Evaluator.
 *
 * Generates an abstract syntax tree (AST) from the provided JavaScript source code
 * and provides methods to analyze the structure of the code and determine whether
 * the entire program is an Immediately Invoked Function Expression (IIFE).
 *
 * @author Konstantinos Pappas <konpap@pressidium.com>
 * @copyright 2025 Pressidium
 */

namespace Pressidium\WP\Performance\Optimizations\Concatenation\JS;

use Pressidium\WP\Performance\Dependencies\Peast\Peast;
use Pressidium\WP\Performance\Dependencies\Peast\Syntax\Node\Program;
use Pressidium\WP\Performance\Dependencies\Peast\Syntax\Node\ExpressionStatement;
use Pressidium\WP\Performance\Dependencies\Peast\Syntax\Node\EmptyStatement;
use Pressidium\WP\Performance\Dependencies\Peast\Syntax\Node\StringLiteral;
use Pressidium\WP\Performance\Dependencies\Peast\Syntax\Node\ParenthesizedExpression;
use Pressidium\WP\Performance\Dependencies\Peast\Syntax\Node\SequenceExpression;
use Pressidium\WP\Performance\Dependencies\Peast\Syntax\Node\UnaryExpression;
use Pressidium\WP\Performance\Dependencies\Peast\Syntax\Node\NewExpression;
use Pressidium\WP\Performance\Dependencies\Peast\Syntax\Node\CallExpression;
use Pressidium\WP\Performance\Dependencies\Peast\Syntax\Node\MemberExpression;
use Pressidium\WP\Performance\Dependencies\Peast\Syntax\Node\FunctionExpression;
use Pressidium\WP\Performance\Dependencies\Peast\Syntax\Node\ArrowFunctionExpression;
use Pressidium\WP\Performance\Dependencies\Peast\Syntax\Node\Expression;

use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
    die( 'Forbidden' );
}

/**
 * JS_Evaluator class.
 *
 * @since 1.0.0
 */
class JS_Evaluator {

    /**
     * @var Program AST representation of the JavaScript source code.
     */
    private Program $ast;

    /**
     * JS_Evaluator constructor.
     *
     * @throw Throwable When the source code cannot be parsed or is invalid.
     *
     * @param string $source JavaScript source code to analyze.
     * @param string $type   Type of the source code, either 'script' or 'module'.
     *                       Defaults to 'script'.
     */
    public function __construct( string $source, string $type = 'script' ) {
        $peast_source_type = $type === 'module'
            ? Peast::SOURCE_TYPE_MODULE
            : Peast::SOURCE_TYPE_SCRIPT;

        $parser = Peast::latest(
            $source,
            array(
                'sourceType' => $peast_source_type,
            )
        );

        $this->ast = $parser->parse();
    }

    /**
     * Return the underlying AST representation of the JavaScript source code.
     *
     * @return Program
     */
    public function get_ast(): Program {
        return $this->ast;
    }

    /**
     * Whether the entire program is an Immediately Invoked Function Expression (IIFE).
     *
     * This ignores directive prologues (e.g. 'use strict') and empty statements,
     * and checks if the program consists of a single expression statement that
     * immediately invokes a function expression.
     *
     * @link https://developer.mozilla.org/en-US/docs/Glossary/IIFE
     *
     * @link https://es5.github.io/#x14.1
     * @link https://262.ecma-international.org/5.1/#sec-14.1
     *
     * @return bool
     */
    public function is_program_an_iife(): bool {
        $statements = $this->strip_directive_prologue_and_empty( $this->ast->getBody() );

        if ( count( $statements ) !== 1 ) {
            // If there are not exactly one statement, it cannot be an IIFE
            return false;
        }

        $statement = $statements[0];

        if ( ! $statement instanceof ExpressionStatement ) {
            // If the single statement is not an ExpressionStatement, it cannot be an IIFE
            return false;
        }

        return $this->is_immediately_invoked_expression( $statement->getExpression() );
    }

    /**
     * Remove Directive Prologue (e.g. 'use strict') and empty statements.
     *
     * We need to remove the directive prologue and empty statements
     * so they don't prevent our single statement checks.
     *
     * Directive prologue is the longest sequence of StringLiteral
     * nodes at the beginning of the program, which is usually used
     * to indicate strict mode (e.g. 'use strict') or other directives.
     *
     * @link https://es5.github.io/#x14.1
     * @link https://262.ecma-international.org/5.1/#sec-14.1
     *
     * @param array<int, mixed> $body Body of the program, which is an array of statements.
     *
     * @return array<int, mixed> Statements without directive prologue and empty statements.
     */
    private function strip_directive_prologue_and_empty( array $body ): array {
        $clean = array();

        foreach ( $body as $statement ) {
            if ( $statement instanceof EmptyStatement ) {
                // Skip empty statements.
                continue;
            }

            if ( $statement instanceof ExpressionStatement && $statement->getExpression() instanceof StringLiteral ) {
                // Skip any top-level StringLiteral node (we treat them as directives)
                continue;
            }

            $clean[] = $statement;
        }

        return $clean;
    }

    /**
     * Whether the specified callee ultimately reduces to a function/arrow expression.
     *
     * @param mixed $callee Callee to check.
     *
     * @return bool
     */
    private function is_callee_a_function_expression( $callee ): bool {
        if ( $callee instanceof FunctionExpression || $callee instanceof ArrowFunctionExpression ) {
            // Callee is a function expression or arrow function expression
            return true;
        }

        if ( $callee instanceof ParenthesizedExpression ) {
            // If the callee is wrapped in parentheses, recursively check the inner expression
            return $this->is_callee_a_function_expression( $callee->getExpression() );
        }

        if ( $callee instanceof MemberExpression ) {
            /*
             * If the callee is a `MemberExpression`, e.g. `(function(){}).call(...)`,
             * or `.apply(...)`, we recursively check the object part of the member expression.
             */
            return $this->is_callee_a_function_expression( $callee->getObject() );
        }

        if ( $callee instanceof SequenceExpression ) {
            /*
             * If the callee is a `SequenceExpression`, e.g. `(0, function(){})()`,
             * we recursively check the last expression in the sequence.
             */
            $expressions = $callee->getExpressions();

            if ( empty( $expressions ) ) {
                return false;
            }

            $last_expression = end( $expressions );
            return $this->is_callee_a_function_expression( $last_expression );
        }

        /*
         * Callee is not a function expression, arrow function expression,
         * or a valid wrapper, so we return `false`
         */
        return false;
    }

    /**
     * Whether the specified expression immediately invokes a function expression.
     *
     * Handles common IIFE patterns, such as:
     *
     * - CallExpression with a callee that is a FunctionExpression or ArrowFunctionExpression.
     * - MemberExpression whose object is a function expression, e.g. `(function(){}).call(...)`
     * - UnaryExpression wrappers (`!`, `+`, `-`, `~`, `void`) around the call, e.g. `!function(){}()`
     * - SequenceExpression where the last expression is the call, e.g. `(0, function(){})()`
     * - NewExpression with FunctionExpression callee, e.g. `new function(){}()`
     *
     * @link https://developer.mozilla.org/en-US/docs/Glossary/IIFE
     *
     * @param Expression $expression Expression to check.
     *
     * @return bool
     */
    private function is_immediately_invoked_expression( Expression $expression ): bool {
        if ( $expression instanceof ParenthesizedExpression ) {
            // Recursively check the inner expression to unwrap parentheses
            return $this->is_immediately_invoked_expression( $expression->getExpression() );
        }

        if ( $expression instanceof UnaryExpression ) {
            /*
             * A `UnaryExpression` can be used to wrap the call, e.g. `!function(){}()`
             * or `void(function(){}())`.
             *
             * We recursively check the argument of the unary expression.
             */
            return $this->is_immediately_invoked_expression( $expression->getArgument() );
        }

        if ( $expression instanceof SequenceExpression ) {
            /*
             * A `SequenceExpression` can be used to wrap the call, e.g. `(0, function(){})()`.
             * We recursively check the last expression in the sequence.
             */
            $expressions = $expression->getExpressions();

            if ( empty( $expressions ) ) {
                return false;
            }

            $last_expression = end( $expressions );
            return $this->is_immediately_invoked_expression( $last_expression );
        }

        if ( $expression instanceof NewExpression ) {
            /*
             * Rarely used, but a `NewExpression` can also be used to
             * invoke a function immediately, e.g. `new function(){}()`.
             *
             * We consider this an IIFE-ish pattern, so we check
             * if the callee itself is a function expression.
             */
            $callee = $expression->getCallee();

            if ( $callee instanceof FunctionExpression ) {
                return true;
            }

            // Arrow functions cannot be used with `new`, so we don't check for them here
            return false;
        }

        if ( $expression instanceof CallExpression ) {
            /*
             * A `CallExpression` can be used to invoke a function expression immediately.
             * We check if the callee is a `FunctionExpression` or `ArrowFunctionExpression`.
             */
            $callee = $expression->getCallee();

            return $this->is_callee_a_function_expression( $callee );
        }

        return false;
    }

}

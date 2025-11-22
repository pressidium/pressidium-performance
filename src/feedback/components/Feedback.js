import { useState, useMemo, useCallback } from '@wordpress/element';
import {
  Flex,
  FlexItem,
  Button,
  RadioControl,
  TextareaControl,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

function Feedback(props) {
  const { deactivationLink } = props;

  const [reason, setReason] = useState('');
  const [comment, setComment] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const textareaLabel = useMemo(() => {
    const reasonToLabelMap = {
      found_better_plugin: __('Please share which plugin', 'pressidium-performance'),
      missing_feature: __('Please describe the feature you need', 'pressidium-performance'),
      other: __('Please share the reason', 'pressidium-performance'),
    };

    return reasonToLabelMap[reason] || __('Provide any additional details', 'pressidium-performance');
  }, [reason]);

  const deactivate = useCallback(() => {
    window.location.href = deactivationLink;
  }, [deactivationLink]);

  const sendFeedback = async () => {
    const { route, nonce } = pressidiumCCFeedbackDetails.api;

    const options = {
      path: route,
      method: 'POST',
      data: {
        nonce,
        reason,
        comment,
      },
    };

    try {
      await apiFetch(options);
    } catch (error) {
      /*
       * Log the error to the console, but don't do anything else.
       * We don't want to block the deactivation of the plugin.
       */
      // eslint-disable-next-line no-console
      console.error(error);
    }
  };

  const submitAndDeactivate = async () => {
    setIsSubmitting(true);
    await sendFeedback();
    setIsSubmitting(false);

    deactivate();
  };

  return (
    <Flex direction="column" gap={4}>
      <FlexItem>
        <RadioControl
          selected={reason}
          options={[
            {
              label: __('I no longer need the plugin', 'pressidium-performance'),
              value: 'no_longer_need',
            },
            {
              label: __('I found a better plugin', 'pressidium-performance'),
              value: 'found_better_plugin',
            },
            {
              label: __('It’s missing a feature I need', 'pressidium-performance'),
              value: 'missing_feature',
            },
            {
              label: __('I couldn’t get the plugin to work', 'pressidium-performance'),
              value: 'could_not_get_to_work',
            },
            {
              label: __('It’s a temporary deactivation', 'pressidium-performance'),
              value: 'temporary_deactivation',
            },
            {
              label: __('Other', 'pressidium-performance'),
              value: 'other',
            },
          ]}
          onChange={(value) => setReason(value)}
        />
      </FlexItem>

      {reason ? (
        <FlexItem>
          <TextareaControl
            label={textareaLabel}
            value={comment}
            onChange={(value) => setComment(value)}
          />
        </FlexItem>
      ) : null}

      <FlexItem>
        <Flex direction="row" gap={4}>
          <FlexItem>
            <Button
              onClick={submitAndDeactivate}
              disabled={!reason || isSubmitting}
              isBusy={isSubmitting}
              isPrimary
            >
              {__('Send feedback & deactivate', 'pressidium-performance')}
            </Button>
          </FlexItem>
          <FlexItem>
            <Button onClick={deactivate}>
              {__('Skip & deactivate', 'pressidium-performance')}
            </Button>
          </FlexItem>
        </Flex>
      </FlexItem>
    </Flex>
  );
}

export default Feedback;

import styled, { css } from 'styled-components';

const StyledSection = styled.section`
  max-width: 620px;
  margin: 0 auto;
  padding-top: 128px;
  text-align: center;

  ${({ $variant }) => $variant === 'hero' && css`
    max-width: 600px;
    padding-top: 0;
  `}

  ${({ $variant }) => $variant === 'intro' && css`
    max-width: 380px;
  `}

  ${({ $variant }) => $variant === 'faq' && css`
    max-width: 720px;
    padding-top: 144px;
  `}
`;

const StyledEyebrow = styled.p`
  font-weight: 500;
  font-size: 0.7rem;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--pressidium-edge-text-muted, #e4e3e6);
  margin: 0 0 12px 0;
`;

const StyledHeading = styled.h3`
  font-weight: 600;
  font-size: 1.5rem;
  color: var(--pressidium-edge-text, #ffffff);
  line-height: 1.3;
  margin: 0;

  ${({ $variant }) => $variant === 'hero' && css`
    font-weight: 500;
    font-size: 1.9rem;
    margin: 0 0 16px 0;
  `}

  ${({ $variant }) => $variant === 'intro' && css`
    font-weight: 500;
    font-size: 1.25rem;
    line-height: 1.5;
  `}
`;

const StyledDescription = styled.p`
  font-size: 0.875rem;
  color: var(--pressidium-edge-text-muted, #e4e3e6);
  line-height: 1.6;
  max-width: 480px;
  margin: 20px auto 0 auto;

  ${({ $variant }) => $variant === 'hero' && css`
    font-size: 0.8rem;
    color: var(--pressidium-edge-text, #ffffff);
    max-width: none;
    margin: 0 0 8px 0;
  `}
`;

function EdgeSection(props) {
  const {
    variant = null,
    level = 3,
    eyebrow = null,
    heading = null,
    description = null,
    className = null,
    children = null,
  } = props;

  return (
    <StyledSection $variant={variant} className={className}>
      {eyebrow ? (
        <StyledEyebrow>
          {eyebrow}
        </StyledEyebrow>
      ) : null}
      {heading ? (
        <StyledHeading as={`h${level}`} $variant={variant}>
          {heading}
        </StyledHeading>
      ) : null}
      {description ? (
        <StyledDescription $variant={variant}>
          {description}
        </StyledDescription>
      ) : null}
      {children}
    </StyledSection>
  );
}

export default EdgeSection;

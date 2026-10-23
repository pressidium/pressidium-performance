import { Icon } from '@wordpress/components';
import { check as CheckIcon } from '@wordpress/icons';
import styled from 'styled-components';

const StyledList = styled.ul`
  display: flex;
  flex-direction: column;
  gap: 28px;
  list-style: none;
  text-align: left;
  margin: 40px 0 0 0;
  padding: 0;
`;

const StyledItem = styled.li`
  display: flex;
  align-items: flex-start;
  gap: 12px;
  margin: 0;
`;

const StyledIcon = styled(Icon)`
  flex-shrink: 0;
  fill: var(--pressidium-edge-success, #22c55e);
  margin-top: -2px;
`;

const StyledTitle = styled.h4`
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  font-weight: 600;
  font-size: 0.875rem;
  color: var(--pressidium-edge-text, #ffffff);
  margin: 0 0 6px 0;
`;

const StyledBadge = styled.span`
  display: inline-block;
  padding: 0 0.8em;
  line-height: 1.8em;
  border-radius: 4px;
  font-weight: 400;
  font-size: 0.7rem;
  color: var(--pressidium-edge-info, #60a5fa);
  border: 1px solid var(--pressidium-edge-info, #60a5fa);
`;

const StyledDescription = styled.p`
  font-size: 0.8125rem;
  color: var(--pressidium-edge-text-muted, #e4e3e6);
  line-height: 1.6;
  margin: 0;
`;

function EdgeFeatureList(props) {
  const { features } = props;

  return (
    <StyledList role="list">
      {features.map(({ title, badge, description }) => (
        <StyledItem key={title}>
          <StyledIcon icon={CheckIcon} size={20} />
          <div>
            <StyledTitle>
              {title}
              {badge ? (
                <StyledBadge>
                  {badge}
                </StyledBadge>
              ) : null}
            </StyledTitle>
            <StyledDescription>
              {description}
            </StyledDescription>
          </div>
        </StyledItem>
      ))}
    </StyledList>
  );
}

export default EdgeFeatureList;

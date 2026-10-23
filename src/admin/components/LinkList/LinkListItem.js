import { Icon } from '@wordpress/components';
import { chevronRight } from '@wordpress/icons';
import styled from 'styled-components';

function hexToRgba(hex, alpha) {
  const sanitized = hex.replace('#', '');
  const r = parseInt(sanitized.substring(0, 2), 16);
  const g = parseInt(sanitized.substring(2, 4), 16);
  const b = parseInt(sanitized.substring(4, 6), 16);
  return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

const StyledItem = styled.li`
  list-style: none;
  border-bottom: 1px solid #e0e0e0;
  margin: 0;

  &:last-child {
    border-bottom: none;
  }
`;

const StyledAnchor = styled.a`
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  text-decoration: none;
  color: inherit;
  cursor: pointer;
  transition: background-color 0.15s ease;

  &:hover {
    background-color: #f6f7f7;
  }

  &:focus-visible {
    outline: 2px solid var(--wp-admin-theme-color, #2271b1);
    outline-offset: -2px;
    border-radius: 8px;
  }
`;

const StyledIconWrapper = styled.div`
  width: 36px;
  height: 36px;
  min-width: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
`;

const StyledContent = styled.div`
  flex: 1;
  min-width: 0;
`;

const StyledTitle = styled.span`
  display: block;
  font-weight: 600;
  font-size: 14px;
  color: #1e1e1e;
  margin-bottom: 2px;
`;

const StyledDescription = styled.span`
  display: block;
  font-size: 12px;
  color: #757575;
  line-height: 1.4;
`;

const StyledChevron = styled.div`
  display: flex;
  align-items: center;
  color: #757575;
  flex-shrink: 0;
`;

function LinkListItem(props) {
  const {
    icon, title, description, url, color,
  } = props;

  return (
    <StyledItem>
      <StyledAnchor href={url} target="_blank" rel="noreferrer noopener">
        <StyledIconWrapper
          aria-hidden="true"
          style={{ backgroundColor: hexToRgba(color, 0.1) }}
        >
          <Icon icon={icon} size={24} style={{ fill: color }} />
        </StyledIconWrapper>
        <StyledContent>
          <StyledTitle>{title}</StyledTitle>
          <StyledDescription>{description}</StyledDescription>
        </StyledContent>
        <StyledChevron aria-hidden="true">
          <Icon icon={chevronRight} size={20} />
        </StyledChevron>
      </StyledAnchor>
    </StyledItem>
  );
}

export default LinkListItem;

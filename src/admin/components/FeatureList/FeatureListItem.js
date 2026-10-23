import { Card, CardBody, Icon } from '@wordpress/components';
import styled from 'styled-components';

const StyledItem = styled.li`
  list-style: none;
`;

const StyledCard = styled(Card)`
  height: 100%;
  background-color: #ffffff;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  box-shadow: none;
`;

const StyledIconWrapper = styled.div`
  display: flex;
  align-items: center;
  margin-bottom: 12px;
`;

const StyledTitle = styled.span`
  display: block;
  font-weight: 600;
  font-size: 14px;
  color: #1e1e1e;
  margin-bottom: 6px;
`;

const StyledDescription = styled.p`
  font-size: 12px;
  color: #757575;
  line-height: 1.4;
  margin: 0 0 8px;

  &:last-child {
    margin-bottom: 0;
  }
`;

function FeatureListItem(props) {
  const {
    icon, title, description, color,
  } = props;

  const paragraphs = Array.isArray(description) ? description : [description];

  return (
    <StyledItem>
      <StyledCard>
        <CardBody>
          <StyledIconWrapper aria-hidden="true">
            <Icon icon={icon} size={32} style={{ fill: color }} />
          </StyledIconWrapper>
          <StyledTitle>{title}</StyledTitle>
          {paragraphs.map((paragraph) => (
            <StyledDescription key={paragraph}>
              {paragraph}
            </StyledDescription>
          ))}
        </CardBody>
      </StyledCard>
    </StyledItem>
  );
}

export default FeatureListItem;

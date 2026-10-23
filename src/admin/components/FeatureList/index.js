import styled from 'styled-components';

import FeatureListItem from './FeatureListItem';

const StyledList = styled.ul`
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  align-items: stretch;
  gap: 16px;
  list-style: none;
  margin: 0;
  padding: 0;
  width: 100%;

  > li {
    min-width: 0;
    margin: 0;
  }
`;

function FeatureList(props) {
  const { children } = props;

  return (
    <StyledList role="list">
      {children}
    </StyledList>
  );
}

export {
  FeatureListItem,
};

export default FeatureList;

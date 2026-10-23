import styled from 'styled-components';

import LinkListItem from './LinkListItem';

const StyledList = styled.ul`
  background-color: #ffffff;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  overflow: hidden;
  list-style: none;
  margin: 0;
  padding: 0;
`;

function LinkList(props) {
  const { children } = props;

  return (
    <StyledList role="list">
      {children}
    </StyledList>
  );
}

export {
  LinkListItem,
};

export default LinkList;

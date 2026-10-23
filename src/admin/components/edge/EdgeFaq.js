import styled from 'styled-components';

const StyledList = styled.ul`
  columns: 2;
  column-gap: 40px;
  list-style: none;
  text-align: left;
  margin: 40px 0 0 0;
  padding: 0;

  @media (max-width: 767px) {
    columns: 1;
  }
`;

const StyledItem = styled.li`
  break-inside: avoid;
  margin: 0 0 28px 0;
`;

const StyledQuestion = styled.h4`
  font-weight: 600;
  font-size: 0.8125rem;
  color: var(--pressidium-edge-text, #ffffff);
  margin: 0 0 8px 0;
`;

const StyledAnswer = styled.p`
  font-size: 0.8125rem;
  color: var(--pressidium-edge-text-muted, #e4e3e6);
  line-height: 1.7;
  margin: 0;
`;

function EdgeFaq(props) {
  const { questions } = props;

  return (
    <StyledList role="list">
      {questions.map(({ question, answer }) => (
        <StyledItem key={question}>
          <StyledQuestion>
            {question}
          </StyledQuestion>
          <StyledAnswer>
            {answer}
          </StyledAnswer>
        </StyledItem>
      ))}
    </StyledList>
  );
}

export default EdgeFaq;

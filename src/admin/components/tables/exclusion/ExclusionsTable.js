import { useContext, useMemo } from '@wordpress/element';
import {
  Flex,
  FlexItem,
  Button,
  TextControl,
  ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
  plus as PlusIcon,
  trash as TrashIcon,
} from '@wordpress/icons';

import styled from 'styled-components';

import SettingsContext from 'store/context';
import * as ActionTypes from 'store/actionTypes';

import Table, { Header, Row, Column } from 'components/Table';

const StyledButton = styled(Button)`
  color: #3c434a;
  min-width: 24px;
  height: 24px;
  padding: 0;
  &:hover {
    color: #0073aa;
  },
`;

function ExclusionsTable(props) {
  const {
    exclusions,
    onAddExclusion,
    onUpdateExclusion,
    onDeleteExclusion,
  } = props;

  const { state, dispatch } = useContext(SettingsContext);

  const hasExclusions = useMemo(() => Array.isArray(exclusions) && exclusions.length > 0, [exclusions]);

  return (
    <Flex direction="column" gap={4}>
      <FlexItem>
        {hasExclusions ? (
          <Table width="100%">
            <Header>
              <Column>
                {__('URL', 'pressidium-performance')}
              </Column>
              <Column style={{ maxWidth: '70px' }}>
                {__('Is Regex?', 'pressidium-performance')}
              </Column>
              <Column style={{ maxWidth: '50px' }}>
                {__('Actions', 'pressidium-performance')}
              </Column>
            </Header>
            {exclusions.map((exclusion, index) => (
              <Row>
                <Column>
                  <TextControl
                    value={exclusion.url}
                    placeholder="Exact URL or pattern to exclude"
                    onChange={(value) => onUpdateExclusion(index, 'url', value)}
                  />
                </Column>
                <Column style={{ maxWidth: '70px' }}>
                  <ToggleControl
                    checked={exclusion.is_regex}
                    onChange={(value) => onUpdateExclusion(index, 'is_regex', value)}
                    className="pressidium-no-margin"
                  />
                </Column>
                <Column style={{ maxWidth: '50px', lineHeight: 1 }}>
                  <StyledButton
                    icon={TrashIcon}
                    label={__('Delete', 'pressidium-performance')}
                    onClick={() => onDeleteExclusion(index)}
                  />
                </Column>
              </Row>
            ))}
          </Table>
        ) : (
          <p>
            {__('No exclusions set.', 'pressidium-performance')}
          </p>
        )}
      </FlexItem>
      <FlexItem>
        <Button
          icon={PlusIcon}
          onClick={onAddExclusion}
          style={{ paddingRight: '10px' }}
          isPrimary
        >
          {__('New Exclusion', 'pressidium-performance')}
        </Button>
      </FlexItem>
    </Flex>
  );
}

export default ExclusionsTable;

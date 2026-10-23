import { useContext, useMemo } from '@wordpress/element';
import {
  Flex,
  FlexItem,
  Button,
  TextControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
  plus as PlusIcon,
  trash as TrashIcon,
} from '@wordpress/icons';

import styled from 'styled-components';

import SettingsContext from 'store/context.js';

import Table, { Header, Row, Column } from 'components/Table/index.js';

const StyledButton = styled(Button)`
  color: #3c434a;
  min-width: 24px;
  height: 24px;
  padding: 0;
  &:hover {
    color: #0073aa;
  },
`;

function EdgeItemsTable(props) {
  const {
    items,
    onAddItem,
    onUpdateItem,
    onDeleteItem,
    labels = {},
  } = props;

  const {
    column = __('Item', 'pressidium-performance'),
    placeholder = __('Enter item', 'pressidium-performance'),
    noItems = __('No items set.', 'pressidium-performance'),
    newItem = __('Add new', 'pressidium-performance'),
  } = labels;

  const { state, dispatch } = useContext(SettingsContext);

  const hasItems = useMemo(() => Array.isArray(items) && items.length > 0, [items]);

  return (
    <Flex direction="column" gap={4}>
      <FlexItem>
        {hasItems ? (
          <Table width="100%">
            <Header>
              <Column>
                {column}
              </Column>
              <Column style={{ maxWidth: '50px' }}>
                {__('Actions', 'pressidium-performance')}
              </Column>
            </Header>
            {items.map((exclusion, index) => (
              <Row>
                <Column>
                  <TextControl
                    value={exclusion}
                    placeholder={placeholder}
                    onChange={(value) => onUpdateItem(index, 'item', value)}
                  />
                </Column>
                <Column style={{ maxWidth: '50px', lineHeight: 1 }}>
                  <StyledButton
                    icon={TrashIcon}
                    label={__('Delete', 'pressidium-performance')}
                    onClick={() => onDeleteItem(index)}
                  />
                </Column>
              </Row>
            ))}
          </Table>
        ) : (
          <span className="pressidium-badge pressidium-badge--neutral">
            {noItems}
          </span>
        )}
      </FlexItem>
      <FlexItem>
        <Button
          icon={PlusIcon}
          onClick={onAddItem}
          style={{ paddingRight: '10px' }}
          isPrimary
        >
          {newItem}
        </Button>
      </FlexItem>
    </Flex>
  );
}

export default EdgeItemsTable;

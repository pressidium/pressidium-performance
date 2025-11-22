import { useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import Table, { Header, Row, Column } from 'components/Table';

function ProcessesTable(props) {
  const { items } = props;

  const hasItems = useMemo(() => Array.isArray(items) && items.length > 0, [items]);

  if (!hasItems) {
    return (
      <p>
        {__('No items in current batch.', 'pressidium-performance')}
      </p>
    )
  }

  return (
    <Table>
      <Header>
        <Column>
          {__('File', 'pressidium-performance')}
        </Column>
        <Column style={{ maxWidth: '50px' }}>
          {__('Type', 'pressidium-performance')}
        </Column>
        <Column style={{ maxWidth: '70px' }}>
          {__('Size', 'pressidium-performance')}
        </Column>
      </Header>

      {items.map(({ location, type, size }, index) => (
        <Row>
          <Column>
            <p>
              {location}
            </p>
          </Column>
          <Column style={{ maxWidth: '50px' }}>
            <p>
              {type.toUpperCase()}
            </p>
          </Column>
          <Column style={{ maxWidth: '70px', lineHeight: 1 }}>
            <p>
              {size}
            </p>
          </Column>
        </Row>
      ))}
    </Table>
  );
}

export default ProcessesTable;

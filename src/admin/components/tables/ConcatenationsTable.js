import { useState, useEffect } from '@wordpress/element';
import { Flex, FlexItem, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

import styled from 'styled-components';

import Table, {
  Header,
  Row,
  Column,
  Pagination
} from 'components/Table';

const LineWrapColumn = styled(Column)`
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
`;

function ConcatenationsTable() {
  const [concatenations, setConcatenations] = useState([]);
  const [isFetching, setIsFetching] = useState(false);
  const [page, setPage] = useState(1);

  const [totalConcatenations, setTotalConcatenations] = useState(0);
  const [totalPages, setTotalPages] = useState(0);

  const fetchConcatenations = async () => {
    const { concatenations_route: route, nonce } = pressidiumPerfAdminDetails.api;

    const options = {
      path: addQueryArgs(route, { nonce, page, per_page: 10 }),
      method: 'GET',
      parse: false, // required to get the headers
    };

    const response = await apiFetch(options);
    const parsedResponse = await response.json();

    if (!('success' in parsedResponse) || !parsedResponse.success || !('data' in parsedResponse)) {
      // Failed to fetch concatenations, bail early
      // eslint-disable-next-line no-console
      console.error('Failed to fetch concatenations', parsedResponse);
      throw new Error('Inavlid response while fetching concatenations');
    }

    const { headers } = response;

    if (!headers.has('X-WP-Total') || !headers.has('X-WP-TotalPages')) {
      // Failed to fetch concatenations, bail early
      // eslint-disable-next-line no-console
      console.error('No pagination headers found', headers);
      throw new Error('No pagination headers found while fetching concatenations');
    }

    return {
      data: parsedResponse.data,
      headers,
    };
  };

  useEffect(() => {
    (async () => {
      setIsFetching(true);

      try {
        const { data, headers } = await fetchConcatenations();

        setConcatenations(data);
        setTotalConcatenations(parseInt(headers.get('X-WP-Total'), 10));
        setTotalPages(parseInt(headers.get('X-WP-TotalPages'), 10));
      } catch (error) {
        // eslint-disable-next-line no-console
        console.error('Could not fetch concatenations', error);
      }

      setIsFetching(false);
    })();
  }, [page]);

  if (concatenations.length === 0) {
    return (
      <p>
        {__('There are no concatenations yet.', 'pressidium-performance')}
      </p>
    );
  }

  return (
    <Flex direction="column" style={{ maxWidth: '100%' }}>
      <FlexItem style={{ overflowX: 'scroll' }}>
        <Table>
          <Header>
            <Column>
              {__('Concatenated URL', 'pressidium-performance')}
            </Column>
            <Column>
              {__('File type', 'pressidium-performance')}
            </Column>
            <Column style={{ minWidth: '280px' }}>
              {__('Aggregated Hash', 'pressidium-performance')}
            </Column>
            <Column>
              {__('Files concatenated', 'pressidium-performance')}
            </Column>
            <Column style={{ minWidth: '340px' }}>
              {__('Size', 'pressidium-performance')}
            </Column>
            <Column>
              {__('Concatenated at', 'pressidium-performance')}
            </Column>
            <Column>
              {__('Last update at', 'pressidium-performance')}
            </Column>
          </Header>

          {concatenations
            .map((concatenation) => (
              <Row>
                <LineWrapColumn>
                  <span>
                    <a
                      href={concatenation.concatenated_uri}
                      target="_blank"
                      rel="noopener noreferrer"
                    >
                      {concatenation.concatenated_uri}
                    </a>
                  </span>
                </LineWrapColumn>
                <Column>
                  <span>
                    {concatenation.type}
                  </span>
                </Column>
                <Column style={{ minWidth: '280px' }}>
                  <span style={{ fontFamily: 'monospace' }}>
                    {concatenation.aggregated_hash}
                  </span>
                </Column>
                <Column>
                  <span>
                    {concatenation.files_count !== null ? concatenation.files_count : 'N/A'}
                  </span>
                </Column>
                <Column style={{ minWidth: '340px' }}>
                  <span>
                    {concatenation.size_diff}
                  </span>
                </Column>
                <Column>
                  <span>
                    {concatenation.created_at}
                  </span>
                </Column>
                <Column>
                  <span>
                    {concatenation.updated_at}
                  </span>
                </Column>
              </Row>
            ))}
        </Table>
      </FlexItem>
      <FlexItem>
        <Flex>
          <FlexItem>
            {isFetching && <Spinner />}
          </FlexItem>
          <FlexItem>
            <Pagination
              currentPage={page}
              numPages={totalPages}
              changePage={setPage}
              totalItems={totalConcatenations}
              style={{ justifyContent: 'flex-end' }}
            />
          </FlexItem>
        </Flex>
      </FlexItem>
    </Flex>
  );
}

export default ConcatenationsTable;

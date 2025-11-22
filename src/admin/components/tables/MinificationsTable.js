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

function MinificationsTable() {
  const [minifications, setMinifications] = useState([]);
  const [isFetching, setIsFetching] = useState(false);
  const [page, setPage] = useState(1);

  const [totalMinifications, setTotalMinifications] = useState(0);
  const [totalPages, setTotalPages] = useState(0);

  const fetchMinifications = async () => {
    const { minifications_route: route, nonce } = pressidiumPerfAdminDetails.api;

    const options = {
      path: addQueryArgs(route, { nonce, page, per_page: 10 }),
      method: 'GET',
      parse: false, // required to get the headers
    };

    const response = await apiFetch(options);
    const parsedResponse = await response.json();

    if (!('success' in parsedResponse) || !parsedResponse.success || !('data' in parsedResponse)) {
      // Failed to fetch minifications, bail early
      // eslint-disable-next-line no-console
      console.error('Failed to fetch minifications', parsedResponse);
      throw new Error('Inavlid response while fetching minifications');
    }

    const { headers } = response;

    if (!headers.has('X-WP-Total') || !headers.has('X-WP-TotalPages')) {
      // Failed to fetch minifications, bail early
      // eslint-disable-next-line no-console
      console.error('No pagination headers found', headers);
      throw new Error('No pagination headers found while fetching minifications');
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
        const { data, headers } = await fetchMinifications();

        setMinifications(data);
        setTotalMinifications(parseInt(headers.get('X-WP-Total'), 10));
        setTotalPages(parseInt(headers.get('X-WP-TotalPages'), 10));
      } catch (error) {
        // eslint-disable-next-line no-console
        console.error('Could not fetch minifications', error);
      }

      setIsFetching(false);
    })();
  }, [page]);

  if (minifications.length === 0) {
    return (
      <p>
        {__('There are no minifications yet.', 'pressidium-performance')}
      </p>
    );
  }

  return (
    <Flex direction="column" style={{ maxWidth: '100%' }}>
      <FlexItem style={{ overflowX: 'scroll' }}>
        <Table>
          <Header>
            <Column>
              {__('Original URL', 'pressidium-performance')}
            </Column>
            <Column>
              {__('Optimized URL', 'pressidium-performance')}
            </Column>
            <Column style={{ minWidth: '280px' }}>
              {__('Hash', 'pressidium-performance')}
            </Column>
            <Column style={{ minWidth: '340px' }}>
              {__('Size', 'pressidium-performance')}
            </Column>
            <Column>
              {__('Minified at', 'pressidium-performance')}
            </Column>
            <Column>
              {__('Last update at', 'pressidium-performance')}
            </Column>
          </Header>

          {minifications.map((minification) => (
            <Row>
              <LineWrapColumn>
                <span>
                  <a
                    href={minification.original_uri}
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    {minification.original_uri}
                  </a>
                </span>
              </LineWrapColumn>
              <LineWrapColumn>
                <span>
                  <a
                    href={minification.optimized_uri}
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    {minification.optimized_uri}
                  </a>
                </span>
              </LineWrapColumn>
              <Column style={{ minWidth: '280px' }}>
                <span style={{ fontFamily: 'monospace' }}>
                  {minification.hash}
                </span>
              </Column>
              <Column style={{ minWidth: '340px' }}>
                <span>
                  {minification.size_diff}
                </span>
              </Column>
              <Column>
                <span>
                  {minification.created_at}
                </span>
              </Column>
              <Column>
                <span>
                  {minification.updated_at}
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
              totalItems={totalMinifications}
              style={{ justifyContent: 'flex-end' }}
            />
          </FlexItem>
        </Flex>
      </FlexItem>
    </Flex>
  );
}

export default MinificationsTable;

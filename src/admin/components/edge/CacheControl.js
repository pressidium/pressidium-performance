import { useState, useCallback } from '@wordpress/element';
import {
  Flex,
  FlexItem,
  Button,
  Card,
  CardBody,
  Icon,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
  trash as TrashIcon,
} from '@wordpress/icons';

import apiFetch from '@wordpress/api-fetch';

import PurgeCacheModal from 'components/modals/PurgeCacheModal';

function CacheControl(props) {
  const { appendNotice } = props;

  const [isPurging, setIsPurging] = useState(false);
  const [isPurgeModalOpen, setIsPurgeModalOpen] = useState(false);

  const openPurgeModal = useCallback(() => setIsPurgeModalOpen(true), []);
  const closePurgeModal = useCallback(() => setIsPurgeModalOpen(false), []);

  const purgeSpecific = useCallback(async (cacheTags, uris) => {
    setIsPurging(true);

    const { edge_cache_purge_route: route, nonce } = pressidiumPerfAdminDetails.api;

    const options = {
      path: route,
      method: 'POST',
      data: {
        nonce,
        cache_tags: cacheTags,
        uris,
      },
    };

    try {
      const response = await apiFetch(options);

      if ('success' in response && response.success) {
        appendNotice({
          message: __('Cache invalidation request sent successfully.', 'pressidium-performance'),
          status: 'success',
          id: 'edge-cache-purge-success',
        });
      } else {
        appendNotice({
          message: __('Could not send cache invalidation request.', 'pressidium-performance'),
          status: 'error',
          id: 'edge-cache-purge-error',
        });
      }
    } catch (error) {
      if ('code' in error && 'message' in error) {
        appendNotice({
          message: error.message,
          status: 'error',
          id: `edge-cache-purge-${error.code}-error`,
        });
      } else {
        appendNotice({
          message: __('Could not send cache invalidation request.', 'pressidium-performance'),
          status: 'error',
          id: 'edge-cache-purge-generic-error',
        });
      }
    } finally {
      setIsPurging(false);
      closePurgeModal();
    }
  }, [closePurgeModal]);

  const purgeAll = useCallback(async () => {
    setIsPurging(true);

    const { edge_cache_purge_all_route: route, nonce } = pressidiumPerfAdminDetails.api;

    const options = {
      path: route,
      method: 'POST',
      data: {
        nonce,
      },
    };

    try {
      const response = await apiFetch(options);

      if ('success' in response && response.success) {
        appendNotice({
          message: __('Cache invalidation request sent successfully.', 'pressidium-performance'),
          status: 'success',
          id: 'edge-cache-purge-all-success',
        });
      } else {
        appendNotice({
          message: __('Could not send cache invalidation request.', 'pressidium-performance'),
          status: 'error',
          id: 'edge-cache-purge-all-error',
        });
      }
    } catch (error) {
      if ('code' in error && 'message' in error) {
        appendNotice({
          message: error.message,
          status: 'error',
          id: `edge-cache-purge-all-${error.code}-error`,
        });
      } else {
        appendNotice({
          message: __('Could not send cache invalidation request.', 'pressidium-performance'),
          status: 'error',
          id: 'edge-cache-purge-all-generic-error',
        });
      }
    } finally {
      setIsPurging(false);
    }
  }, []);

  return (
    <>
      <Card style={{ width: '400px', maxWidth: '100%' }}>
        <CardBody>
          <div className="pressidium-edge-cache-card__icon-title">
            <Icon icon={TrashIcon} />
            <span className="pressidium-edge-cache-card__title">
              {__('Purge cache', 'pressidium-performance')}
            </span>
          </div>
          <p className="pressidium-edge-cache-card__description">
            {__('Purge cached content from your site.', 'pressidium-performance')}
          </p>
          <Flex gap={2} justify="flex-start">
            <FlexItem>
              <Button
                variant="secondary"
                onClick={openPurgeModal}
                disabled={isPurging}
              >
                {__('Purge cache tags or URIs', 'pressidium-performance')}
              </Button>
            </FlexItem>
            <FlexItem>
              <Button
                variant="secondary"
                onClick={purgeAll}
                disabled={isPurging}
                isBusy={isPurging}
              >
                {__('Purge everything', 'pressidium-performance')}
              </Button>
            </FlexItem>
          </Flex>
        </CardBody>
      </Card>

      {isPurgeModalOpen && (
        <PurgeCacheModal
          onClose={closePurgeModal}
          onPurge={purgeSpecific}
          isPurging={isPurging}
        />
      )}
    </>
  );
}

export default CacheControl;

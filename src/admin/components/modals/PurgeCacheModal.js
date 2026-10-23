import { useState, useCallback } from '@wordpress/element';
import {
  Button,
  Card,
  CardBody,
  Flex,
  FlexItem,
  Icon,
  Modal,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
  tag as TagIcon,
  link as LinkIcon,
  trash as TrashIcon,
} from '@wordpress/icons';

import EdgeItemsTable from 'components/tables/edge/EdgeItemsTable';

function PurgeCacheModal({ onClose, onPurge, isPurging }) {
  const [cacheTags, setCacheTags] = useState([]);
  const [uris, setUris] = useState([]);

  const onAddCacheTag = useCallback(() => setCacheTags((prev) => [...prev, '']), []);
  const onUpdateCacheTag = useCallback((index, _key, value) => {
    setCacheTags((prev) => [
      ...prev.slice(0, index),
      value,
      ...prev.slice(index + 1),
    ]);
  }, []);
  const onDeleteCacheTag = useCallback((index) => {
    setCacheTags((prev) => prev.filter((_, i) => i !== index));
  }, []);

  const onAddUri = useCallback(() => setUris((prev) => [...prev, '']), []);
  const onUpdateUri = useCallback((index, _key, value) => {
    setUris((prev) => [
      ...prev.slice(0, index),
      value,
      ...prev.slice(index + 1),
    ]);
  }, []);
  const onDeleteUri = useCallback((index) => {
    setUris((prev) => prev.filter((_, i) => i !== index));
  }, []);

  const handlePurge = useCallback(() => {
    onPurge(cacheTags, uris);
  }, [onPurge, cacheTags, uris]);

  return (
    <Modal
      title={__('Choose what to purge', 'pressidium-performance')}
      onRequestClose={onClose}
    >
      <Flex direction="column" gap={6}>
        <FlexItem>
          <div className="pressidium-edge-cache-grid">
            <Card>
              <CardBody>
                <div className="pressidium-edge-cache-card__icon-title">
                  <Icon icon={TagIcon} />
                  <span className="pressidium-edge-cache-card__title">
                    {__('Cache Tags', 'pressidium-performance')}
                  </span>
                </div>
                <p className="pressidium-edge-cache-card__description">
                  {__('Add cache tags to purge specific content.', 'pressidium-performance')}
                </p>
                <EdgeItemsTable
                  items={cacheTags}
                  onAddItem={onAddCacheTag}
                  onUpdateItem={onUpdateCacheTag}
                  onDeleteItem={onDeleteCacheTag}
                  labels={{
                    column: __('Cache Tag', 'pressidium-performance'),
                    placeholder: __('Cache tag to purge', 'pressidium-performance'),
                    noItems: __('No cache tags set.', 'pressidium-performance'),
                    newItem: __('Add cache tag', 'pressidium-performance'),
                  }}
                />
              </CardBody>
            </Card>

            <Card>
              <CardBody>
                <div className="pressidium-edge-cache-card__icon-title">
                  <Icon icon={LinkIcon} />
                  <span className="pressidium-edge-cache-card__title">
                    {__('URLs', 'pressidium-performance')}
                  </span>
                </div>
                <p className="pressidium-edge-cache-card__description">
                  {__('Add URLs (or paths) to purge specific pages.', 'pressidium-performance')}
                </p>
                <EdgeItemsTable
                  items={uris}
                  onAddItem={onAddUri}
                  onUpdateItem={onUpdateUri}
                  onDeleteItem={onDeleteUri}
                  labels={{
                    column: __('URL', 'pressidium-performance'),
                    placeholder: __('URL to purge', 'pressidium-performance'),
                    noItems: __('No URLs set.', 'pressidium-performance'),
                    newItem: __('Add URL', 'pressidium-performance'),
                  }}
                />
              </CardBody>
            </Card>
          </div>
        </FlexItem>

        <FlexItem>
          <Flex justify="flex-end">
            <FlexItem>
              <Button variant="tertiary" onClick={onClose}>
                {__('Cancel', 'pressidium-performance')}
              </Button>
            </FlexItem>
            <FlexItem>
              <Button
                variant="primary"
                icon={TrashIcon}
                onClick={handlePurge}
                disabled={isPurging}
                isBusy={isPurging}
              >
                {__('Purge', 'pressidium-performance')}
              </Button>
            </FlexItem>
          </Flex>
        </FlexItem>
      </Flex>
    </Modal>
  );
}

export default PurgeCacheModal;

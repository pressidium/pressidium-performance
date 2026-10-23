import { __ } from '@wordpress/i18n';
import {
  Button,
  Flex,
  FlexItem,
  Modal,
} from '@wordpress/components';

function DisconnectEdgeModal(props) {
  const { isOpen, onClose, disconnectFromEdge } = props;

  if (!isOpen) {
    return null;
  }

  return (
    <Modal
      title={__('Disconnect from Pressidium EDGE?', 'pressidium-performance')}
      onRequestClose={onClose}
    >
      <Flex direction="column">
        <p>
          <strong>
            {__('This action cannot be undone.', 'pressidium-performance')}
          </strong>
          &nbsp;
          {
            __(
              'You are about to disconnect your site from Pressidium EDGE. Are you sure you want to proceed?',
              'pressidium-performance',
            )
          }
        </p>
        <Flex justify="flex-end">
          <FlexItem>
            <Button
              variant="tertiary"
              onClick={onClose}
            >
              {__('Cancel', 'pressidium-performance')}
            </Button>
          </FlexItem>
          <FlexItem>
            <Button
              variant="primary"
              className="is-destructive"
              onClick={() => {
                disconnectFromEdge();
                onClose();
              }}
            >
              {__('Disconnect', 'pressidium-performance')}
            </Button>
          </FlexItem>
        </Flex>
      </Flex>
    </Modal>
  );
}

export default DisconnectEdgeModal;

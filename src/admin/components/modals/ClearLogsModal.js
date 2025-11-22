import { __ } from '@wordpress/i18n';
import {
  Button,
  Flex,
  FlexItem,
  Modal,
} from '@wordpress/components';

function ClearLogsModal(props) {
  const { isOpen, onClose, clearLogs } = props;

  if (!isOpen) {
    return null;
  }

  return (
    <Modal
      title={__('Clear logs?', 'pressidium-performance')}
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
              'You are about to clear all logs. Are you sure you want to proceed?',
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
                clearLogs();
                onClose();
              }}
            >
              {__('Clear logs', 'pressidium-performance')}
            </Button>
          </FlexItem>
        </Flex>
      </Flex>
    </Modal>
  );
}

export default ClearLogsModal;

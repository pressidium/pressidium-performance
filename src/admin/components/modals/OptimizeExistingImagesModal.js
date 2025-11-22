import { __ } from '@wordpress/i18n';
import {
  Button,
  Flex,
  FlexItem,
  Modal,
} from '@wordpress/components';

function OptimizeExistingImagesModal(props) {
  const { isOpen, onClose, optimizeExistingImages } = props;

  if (!isOpen) {
    return null;
  }

  return (
    <Modal
      title={__('Optimize images?', 'pressidium-performance')}
      onRequestClose={onClose}
    >
      <Flex direction="column">
        <p>
          <strong>
            {__('This might take a while.', 'pressidium-performance')}
          </strong>
          &nbsp;
          {__('You are about to start optimizing images in the background. Are you sure you want to proceed?', 'pressidium-performance')}
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
              onClick={() => {
                optimizeExistingImages();
                onClose();
              }}
            >
              {__('Begin optimizations', 'pressidium-performance')}
            </Button>
          </FlexItem>
        </Flex>
      </Flex>
    </Modal>
  );
}

export default OptimizeExistingImagesModal;

import { useState, useContext, useCallback } from '@wordpress/element';
import {
  Panel,
  PanelBody,
  PanelRow,
  Flex,
  FlexItem,
  ToggleControl,
  RadioControl,
  RangeControl,
  Button,
  ExternalLink,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
  settings as SettingsIcon,
  replace as ReplaceIcon,
  image as ImageIcon,
  gallery as GalleryIcon,
  undo as UndoIcon,
} from '@wordpress/icons';

import SettingsContext from 'store/context';
import * as ActionTypes from 'store/actionTypes';

import ImageFormatsTable from 'components/tables/ImageFormatsTable';
import ImageOptimizationExclusionsTable from 'components/tables/exclusion/ImageOptimizationExclusionsTable';
import OptimizeExistingImagesModal from 'components/modals/OptimizeExistingImagesModal';
import RevertOptimizedImagesModal from 'components/modals/RevertOptimizedImagesModal';

function ImageOptimizationTab(props) {
  const { state, dispatch } = useContext(SettingsContext);

  const { optimizeExistingImages, revertOptimizedImages } = props;

  const [isOptimizeExistingImagesModalOpen, setIsOptimizeExistingImagesModalOpen] = useState(false);
  const [isRevertOptimizedImagesModalOpen, setIsRevertOptimizedImagesModalOpen] = useState(false);

  const openOptimizeExistingImagesModal = useCallback(() => setIsOptimizeExistingImagesModalOpen(true), []);
  const closeOptimizeExistingImagesModal = useCallback(() => setIsOptimizeExistingImagesModalOpen(false), []);

  const openRevertOptimizedImagesModal = useCallback(() => setIsRevertOptimizedImagesModalOpen(true), []);
  const closeRevertOptimizedImagesModal = useCallback(() => setIsRevertOptimizedImagesModalOpen(false), []);

  const onImageOptimizationSettingChange = useCallback((key, value) => {
    dispatch({
      type: ActionTypes.SET_IMAGE_OPTIMIZATION_SETTING,
      payload: {
        key,
        value,
      },
    });
  }, []);

  const marks = [
    {
      value: 10,
      label: 'Most Lossy',
    },
    {
      value: 75,
      label: 'Balanced',
    },
    {
      value: 100,
      label: 'Lossless',
    },
  ];

  return (
    <Panel>
      <PanelBody initialOpen>
        <PanelRow>
          <p>
            {__('The image optimization feature compresses your images and automatically converts them to modern formats like WebP or AVIF for faster loading and sharp images.', 'pressidium-performance')}
          </p>
        </PanelRow>
      </PanelBody>
      <PanelBody
        title={__('Universal settings', 'pressidium-performance')}
        icon={SettingsIcon}
        initialOpen
      >
        <PanelRow>
          <ToggleControl
            label={__('Auto optimize', 'pressidium-performance')}
            help={state.imageOptimization.autoOptimize
              ? __('Will optimize new images automatically', 'pressidium-performance')
              : __('Won\'t optimize new images', 'pressidium-performance')}
            checked={state.imageOptimization.autoOptimize}
            className="pressidium-toggle-control"
            onChange={(value) => onImageOptimizationSettingChange('autoOptimize', value)}
          />
        </PanelRow>
        <PanelRow>
          <ToggleControl
            label={__('Keep original files', 'pressidium-performance')}
            help={state.imageOptimization.keepOriginalFiles
              ? __('Will retain the originally uploaded images', 'pressidium-performance')
              : __('Will delete the originally uploaded images', 'pressidium-performance')}
            checked={state.imageOptimization.keepOriginalFiles}
            className="pressidium-toggle-control"
            onChange={(value) => onImageOptimizationSettingChange('keepOriginalFiles', value)}
          />
        </PanelRow>
        <PanelRow>
          <Flex direction="column" gap={0}>
            <FlexItem>
              <RadioControl
                label={__('Preferred image editor', 'pressidium-performance')}
                help={__('Prioritize an image editor (GD or Imagick)', 'pressidium-performance')}
                selected={state.imageOptimization.preferredImageEditor}
                options={[
                  { label: __('Auto', 'pressidium-performance'), value: 'auto' },
                  { label: __('GD', 'pressidium-performance'), value: 'gd' },
                  { label: __('Imagick', 'pressidium-performance'), value: 'imagick' },
                ]}
                onChange={(value) => onImageOptimizationSettingChange('preferredImageEditor', value)}
              />
            </FlexItem>
            <FlexItem>
              <ExternalLink href="https://github.com/pressidium/pressidium-performance/wiki/PHP-Image-Libraries">
                {__('Learn more about GD and Imagick ', 'pressidium-performance')}
              </ExternalLink>
            </FlexItem>
          </Flex>
        </PanelRow>
        <PanelRow>
          <Flex
            direction="column"
            style={{ width: '100%' }}
          >
            <FlexItem style={{ width: '100%', maxWidth: '600px' }}>
              <RangeControl
                label="Quality"
                value={state.imageOptimization.quality}
                initialPosition={75}
                onChange={(value) => onImageOptimizationSettingChange('quality', value)}
                min={10}
                max={100}
                marks={marks}
              />
            </FlexItem>
          </Flex>
        </PanelRow>
      </PanelBody>
      <PanelBody
        title={__('Format-specific settings', 'pressidium-performance')}
        icon={ReplaceIcon}
        initialOpen
      >
        <PanelRow>
          <ImageFormatsTable />
        </PanelRow>
      </PanelBody>
      <PanelBody
        title={__('Exclusions', 'pressidium-performance')}
        icon={ImageIcon}
        initialOpen
      >
        <PanelRow>
          <ImageOptimizationExclusionsTable />
        </PanelRow>
      </PanelBody>
      <PanelBody opened>
        <PanelRow>
          <Flex gap={3} wrap>
            <FlexItem>
              <Button
                variant="secondary"
                icon={GalleryIcon}
                onClick={openOptimizeExistingImagesModal}
                disabled={false}
                style={{ paddingRight: '10px' }}
              >
                {__('Optimize existing images now', 'pressidium-performance')}
              </Button>
              <OptimizeExistingImagesModal
                isOpen={isOptimizeExistingImagesModalOpen}
                onClose={closeOptimizeExistingImagesModal}
                optimizeExistingImages={optimizeExistingImages}
              />
            </FlexItem>
            <FlexItem>
              <Button
                variant="secondary"
                icon={UndoIcon}
                onClick={openRevertOptimizedImagesModal}
                disabled={false}
                isDestructive
                style={{ paddingRight: '10px' }}
              >
                {__('Revert optimized images', 'pressidium-performance')}
              </Button>
              <RevertOptimizedImagesModal
                isOpen={isRevertOptimizedImagesModalOpen}
                onClose={closeRevertOptimizedImagesModal}
                revertOptimizedImages={revertOptimizedImages}
              />
            </FlexItem>
          </Flex>
        </PanelRow>
      </PanelBody>
    </Panel>
  );
}

export default ImageOptimizationTab;

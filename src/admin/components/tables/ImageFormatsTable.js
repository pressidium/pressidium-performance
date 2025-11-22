import { useContext, useCallback } from '@wordpress/element';
import { RadioControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import SettingsContext from 'store/context';
import * as ActionTypes from 'store/actionTypes';

import imageMimeTypes from 'common/imageMimeTypes';

import Table, { Header, Row, Column } from 'components/Table';

function ImageFormatsTable() {
  const { state, dispatch } = useContext(SettingsContext);

  const onImageFormatSettingChange = useCallback((mimeType, key, value) => {
    dispatch({
      type: ActionTypes.SET_IMAGE_FORMAT_SETTING,
      payload: {
        mimeType,
        key,
        value,
      },
    });
  }, []);

  return (
    <Table style={{ width: '600px' }}>
      <Header>
        <Column style={{ maxWidth: '90px' }}>
          {__('Image type', 'pressidium-performance')}
        </Column>
        <Column style={{ maxWidth: '120px' }}>
          {__('Should optimize', 'pressidium-performance')}
        </Column>
        <Column>
          {__('Convert to', 'pressidium-performance')}
        </Column>
      </Header>
      {imageMimeTypes.map(({ label, mimeType }) => {
        const { shouldOptimize = false, convertTo = 'image/webp' } = state.imageOptimization.formats[mimeType];

        return (
          <Row>
            <Column style={{ maxWidth: '90px' }}>
              {label}
            </Column>
            <Column style={{ maxWidth: '120px', justifyContent: 'center' }}>
              <ToggleControl
                checked={shouldOptimize}
                className="pressidium-toggle-control pressidium-no-margin"
                onChange={(value) => onImageFormatSettingChange(mimeType, 'shouldOptimize', value)}
              />
            </Column>
            <Column>
              <div style={{ opacity: shouldOptimize ? 1 : 0.4 }}>
                <RadioControl
                  label={__('Convert to', 'pressidium-performance')}
                  hideLabelFromVision
                  selected={convertTo}
                  className="pressidium-horizontal-radio"
                  disabled={!shouldOptimize}
                  options={[
                    {label: __('WebP', 'pressidium-performance'), value: 'image/webp'},
                    {label: __('AVIF', 'pressidium-performance'), value: 'image/avif'},
                  ]}
                  onChange={(value) => onImageFormatSettingChange(mimeType, 'convertTo', value)}
                />
              </div>
            </Column>
          </Row>
        );
      })}
    </Table>
  );
}

export default ImageFormatsTable;

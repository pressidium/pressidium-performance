import { useContext, useCallback } from '@wordpress/element';
import {
  Panel,
  PanelBody,
  PanelRow,
  ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
  settings as SettingsIcon,
  brush as BrushIcon,
  code as CodeIcon,
  archive as ArchiveIcon,
} from '@wordpress/icons';

import SettingsContext from 'store/context';
import * as ActionTypes from 'store/actionTypes';

import MinificationExclusionsTable from 'components/tables/exclusion/MinificationExclusionsTable';
import MinificationsTable from 'components/tables/MinificationsTable';

function MinificationTab(props) {
  const { state, dispatch } = useContext(SettingsContext);

  const onMinificationSettingChange = useCallback((key, value) => {
    dispatch({
      type: ActionTypes.SET_MINIFICATION_SETTING,
      payload: {
        key,
        value,
      },
    });
  }, []);

  return (
    <Panel>
      <PanelBody initialOpen>
        <PanelRow>
          <p>
            {__('The minification feature reduces the size of your website’s CSS and JavaScript files by removing unnecessary spaces, comments, and formatting. This makes your pages load faster and improves overall performance without changing how your website looks or works.', 'pressidium-performance')}
          </p>
        </PanelRow>
      </PanelBody>
      <PanelBody
        title={__('Configuration', 'pressidium-performance')}
        icon={SettingsIcon}
        initialOpen
      >
        <PanelRow>
          <ToggleControl
            label={__('Minify scripts', 'pressidium-performance')}
            help={state.minification.minifyJS
              ? __('Will minify JS files', 'pressidium-performance')
              : __('Won\'t minify JS files', 'pressidium-performance')}
            checked={state.minification.minifyJS}
            className="pressidium-toggle-control"
            onChange={(value) => onMinificationSettingChange('minifyJS', value)}
          />
        </PanelRow>
        <PanelRow>
          <ToggleControl
            label={__('Minify stylesheets', 'pressidium-performance')}
            help={state.minification.minifyCSS
              ? __('Will minify CSS files', 'pressidium-performance')
              : __('Won\'t minify CSS files', 'pressidium-performance')}
            checked={state.minification.minifyCSS}
            className="pressidium-toggle-control"
            onChange={(value) => onMinificationSettingChange('minifyCSS', value)}
          />
        </PanelRow>
      </PanelBody>

      <PanelBody
        title={__('Script exclusions', 'pressidium-performance')}
        icon={CodeIcon}
        initialOpen
      >
        <PanelRow>
          <MinificationExclusionsTable category="js" />
        </PanelRow>
      </PanelBody>

      <PanelBody
        title={__('Stylesheet exclusions', 'pressidium-performance')}
        icon={BrushIcon}
        initialOpen
      >
        <PanelRow>
          <MinificationExclusionsTable category="css" />
        </PanelRow>
      </PanelBody>

      <PanelBody
        title={__('Minifications', 'pressidium-performance')}
        icon={ArchiveIcon}
        initialOpen
      >
        <PanelRow>
          <MinificationsTable />
        </PanelRow>
      </PanelBody>

    </Panel>
  );
}

export default MinificationTab;

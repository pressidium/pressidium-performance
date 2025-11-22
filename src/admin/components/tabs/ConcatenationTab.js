import { useState, useContext, useCallback } from '@wordpress/element';
import {
  Panel,
  PanelBody,
  PanelRow,
  ToggleControl,
  Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
  settings as SettingsIcon,
  brush as BrushIcon,
  code as CodeIcon,
  archive as ArchiveIcon,
} from '@wordpress/icons';

import styled from 'styled-components';

import SettingsContext from 'store/context';
import * as ActionTypes from 'store/actionTypes';

import ConcatenationExclusionsTable from 'components/tables/exclusion/ConcatenationExclusionsTable';
import ConcatenationsTable from 'components/tables/ConcatenationsTable';

const StyledNotice = styled(Notice)`
  margin: 0 !important;
  border-width: 1px;
  border-style: solid;
  border-color: #e0e0e0;
  border-bottom: none;
  box-shadow: inset 4px 0 0 0 #f0b849;
`;

function ConcatenationTab(props) {
  const { state, dispatch } = useContext(SettingsContext);

  const [noticeDismissed, setNoticeDismissed] = useState(false);

  const onConcatenationSettingChange = useCallback((key, value) => {
    dispatch({
      type: ActionTypes.SET_CONCATENATION_SETTING,
      payload: {
        key,
        value,
      },
    });
  }, []);

  const dismissNotice = useCallback(() => setNoticeDismissed(true), []);

  return (
    <>
      {!noticeDismissed ? (
        <StyledNotice
          status="warning"
          politeness="polite"
          onDismiss={dismissNotice}
        >
          <strong>
            {__('Heads up! This is a beta feature.', 'pressidium-performance')}
          </strong>
          &nbsp;
          {__('Concatenation might not play nicely with every theme or plugin. Use it with caution. If something breaks, try excluding the scripts or stylesheets causing trouble, or disable concatenation entirely.', 'pressidium-performance')}
        </StyledNotice>
      ) : null}
      <Panel>
        <PanelBody initialOpen>
          <PanelRow>
            <p>
              {__('The concatenation feature merges multiple CSS and JavaScript files into a single file, reducing the number of HTTP requests your website needs to make. This helps speed up your website, especially for visitors on slower connections.', 'pressidium-performance')}
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
              label={__('Concatenate scripts', 'pressidium-performance')}
              help={state.concatenation.concatenateJS
                ? __('Will concatenate JS files', 'pressidium-performance')
                : __('Won\'t concatenate JS files', 'pressidium-performance')}
              checked={state.concatenation.concatenateJS}
              className="pressidium-toggle-control"
              onChange={(value) => onConcatenationSettingChange('concatenateJS', value)}
            />
          </PanelRow>
          <PanelRow>
            <ToggleControl
              label={__('Concatenate stylesheets', 'pressidium-performance')}
              help={state.concatenation.concatenateCSS
                ? __('Will concatenate CSS files', 'pressidium-performance')
                : __('Won\'t concatenate CSS files', 'pressidium-performance')}
              checked={state.concatenation.concatenateCSS}
              className="pressidium-toggle-control"
              onChange={(value) => onConcatenationSettingChange('concatenateCSS', value)}
            />
          </PanelRow>
        </PanelBody>

        <PanelBody
          title={__('Script exclusions', 'pressidium-performance')}
          icon={CodeIcon}
          initialOpen
        >
          <PanelRow>
            <ConcatenationExclusionsTable category="js" />
          </PanelRow>
        </PanelBody>

        <PanelBody
          title={__('Stylesheet exclusions', 'pressidium-performance')}
          icon={BrushIcon}
          initialOpen
        >
          <PanelRow>
            <ConcatenationExclusionsTable category="css" />
          </PanelRow>
        </PanelBody>

        <PanelBody
          title={__('', 'pressidium-performance')}
          icon={ArchiveIcon}
          initialOpen
        >
          <PanelRow>
            <ConcatenationsTable />
          </PanelRow>
        </PanelBody>
      </Panel>
    </>
  );
}

export default ConcatenationTab;

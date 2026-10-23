import NonPressidiumTab from 'components/edge/NonPressidiumTab.js';
import PressidiumTab from 'components/edge/PressidiumTab.js';

function EdgeTab(props) {
  const { is_pressidium_platform: isPressidiumPlatform } = pressidiumPerfAdminDetails;

  if (isPressidiumPlatform) {
    return <PressidiumTab {...props} />;
  }

  return <NonPressidiumTab {...props} />;
}

export default EdgeTab;

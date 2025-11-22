import * as ActionTypes from './actionTypes';

function settingsReducer(state, action) {
  switch (action.type) {
    case ActionTypes.SET_SETTINGS:
      return {
        ...state,
        ...action.payload,
      };

    case ActionTypes.SET_MINIFICATION_SETTING:
      return {
        ...state,
        minification: {
          ...state.minification,
          [action.payload.key]: action.payload.value,
        },
      };

    case ActionTypes.SET_CONCATENATION_SETTING:
      return {
        ...state,
        concatenation: {
          ...state.concatenation,
          [action.payload.key]: action.payload.value,
        },
      };

    case ActionTypes.SET_IMAGE_FORMAT_SETTING:
      return {
        ...state,
        imageOptimization: {
          ...state.imageOptimization,
          formats: {
            ...state.imageOptimization.formats,
            [action.payload.mimeType]: {
              ...state.imageOptimization.formats[action.payload.mimeType],
              [action.payload.key]: action.payload.value,
            },
          },
        },
      };

    case ActionTypes.ADD_MINIFICATION_EXCLUSION:
      return {
        ...state,
        minification: {
          ...state.minification,
          exclusions: {
            ...state.minification.exclusions,
            [action.payload.category]: [
              ...state.minification.exclusions[action.payload.category],
              {
                url: '',
                is_regex: false,
              },
            ],
          },
        },
      };

    case ActionTypes.UPDATE_MINIFICATION_EXCLUSION:
      return {
        ...state,
        minification: {
          ...state.minification,
          exclusions: {
            ...state.minification.exclusions,
            [action.payload.category]: [
              ...state.minification.exclusions[action.payload.category].slice(0, action.payload.index),
              {
                ...state.minification.exclusions[action.payload.category][action.payload.index],
                [action.payload.key]: action.payload.value,
              },
              ...state.minification.exclusions[action.payload.category].slice(action.payload.index + 1),
            ],
          },
        },
      };

    case ActionTypes.DELETE_MINIFICATION_EXCLUSION:
      return {
        ...state,
        minification: {
          ...state.minification,
          exclusions: {
            ...state.minification.exclusions,
            [action.payload.category]: [
              ...state.minification.exclusions[action.payload.category].slice(0, action.payload.index),
              ...state.minification.exclusions[action.payload.category].slice(action.payload.index + 1),
            ],
          },
        },
      };

    case ActionTypes.ADD_CONCATENATION_EXCLUSION:
      return {
        ...state,
        concatenation: {
          ...state.concatenation,
          exclusions: {
            ...state.concatenation.exclusions,
            [action.payload.category]: [
              ...state.concatenation.exclusions[action.payload.category],
              {
                url: '',
                is_regex: false,
              },
            ],
          },
        },
      };

    case ActionTypes.UPDATE_CONCATENATION_EXCLUSION:
      return {
        ...state,
        concatenation: {
          ...state.concatenation,
          exclusions: {
            ...state.concatenation.exclusions,
            [action.payload.category]: [
              ...state.concatenation.exclusions[action.payload.category].slice(0, action.payload.index),
              {
                ...state.concatenation.exclusions[action.payload.category][action.payload.index],
                [action.payload.key]: action.payload.value,
              },
              ...state.concatenation.exclusions[action.payload.category].slice(action.payload.index + 1),
            ],
          },
        },
      };

    case ActionTypes.DELETE_CONCATENATION_EXCLUSION:
      return {
        ...state,
        concatenation: {
          ...state.concatenation,
          exclusions: {
            ...state.concatenation.exclusions,
            [action.payload.category]: [
              ...state.concatenation.exclusions[action.payload.category].slice(0, action.payload.index),
              ...state.concatenation.exclusions[action.payload.category].slice(action.payload.index + 1),
            ],
          },
        },
      };

    case ActionTypes.ADD_IMAGE_OPTIMIZATION_EXCLUSION:
      return {
        ...state,
        imageOptimization: {
          ...state.imageOptimization,
          exclusions: [
            ...state.imageOptimization.exclusions,
            {
              url: '',
              is_regex: false,
            },
          ],
        },
      };

    case ActionTypes.UPDATE_IMAGE_OPTIMIZATION_EXCLUSION:
      return {
        ...state,
        imageOptimization: {
          ...state.imageOptimization,
          exclusions: [
            ...state.imageOptimization.exclusions.slice(0, action.payload.index),
            {
              ...state.imageOptimization.exclusions[action.payload.index],
              [action.payload.key]: action.payload.value,
            },
            ...state.imageOptimization.exclusions.slice(action.payload.index + 1),
          ],
        },
      };

    case ActionTypes.DELETE_IMAGE_OPTIMIZATION_EXCLUSION:
      return {
        ...state,
        imageOptimization: {
          ...state.imageOptimization,
          exclusions: [
            ...state.imageOptimization.exclusions.slice(0, action.payload.index),
            ...state.imageOptimization.exclusions.slice(action.payload.index + 1),
          ],
        },
      };

    case ActionTypes.SET_IMAGE_OPTIMIZATION_SETTING:
      return {
        ...state,
        imageOptimization: {
          ...state.imageOptimization,
          [action.payload.key]: action.payload.value,
        },
      };

    default:
      return state;
  }
}

export default settingsReducer;

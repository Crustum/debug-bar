export const MINI_POSITIONS = [
  'bottom-right',
  'bottom-left',
  'top-right',
  'top-left',
  'middle-right',
  'middle-left',
];

export const MINI_POSITION_DEFAULT = 'bottom-right';

export const MINI_ACTIONS = ['bar', 'inspector'];

export const MINI_ACTION_DEFAULT = 'bar';

export const MINI_STORAGE_KEY = 'debugbar.mini.position.v1';

export function sanitizeMiniPosition(
  position,
  fallback = MINI_POSITION_DEFAULT,
) {
  if (typeof position === 'string' && MINI_POSITIONS.includes(position)) {
    return position;
  }
  if (typeof fallback === 'string' && MINI_POSITIONS.includes(fallback)) {
    return fallback;
  }

  return MINI_POSITION_DEFAULT;
}

export function sanitizeMiniAction(action, fallback = MINI_ACTION_DEFAULT) {
  if (typeof action === 'string' && MINI_ACTIONS.includes(action)) {
    return action;
  }
  if (typeof fallback === 'string' && MINI_ACTIONS.includes(fallback)) {
    return fallback;
  }

  return MINI_ACTION_DEFAULT;
}

export function miniPositionLabel(position) {
  if (typeof position !== 'string' || position === '') {
    return position;
  }

  return position.replaceAll('-', ' ');
}

export function buildMiniCommands() {
  return [
    ...MINI_POSITIONS.map((position) => ({
      id: `mini:${position}`,
      label: `Move mini to ${miniPositionLabel(position)}`,
      hint: 'Mini',
    })),
    {
      id: 'mini:server-default',
      label: 'Reset mini position to server default',
      hint: 'Mini',
    },
    {
      id: 'mini:open-bar',
      label: 'Mini opens toolbar',
      hint: 'Mini',
    },
    {
      id: 'mini:open-inspector',
      label: 'Mini opens inspector',
      hint: 'Mini',
    },
    {
      id: 'mini:open-server-default',
      label: 'Reset mini action to server default',
      hint: 'Mini',
    },
  ];
}

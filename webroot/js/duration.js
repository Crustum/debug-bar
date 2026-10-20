export function formatDuration(value) {
  if (value === null || value === '' || !Number.isFinite(Number(value)))
    return '—';

  const milliseconds = Math.max(0, Number(value));
  if (!Number.isFinite(milliseconds)) return '—';

  if (milliseconds >= 3600000) {
    const hours = Math.floor(milliseconds / 3600000);
    const minutes = Math.floor((milliseconds - hours * 3600000) / 60000);
    if (minutes > 0) return `${hours} h ${minutes} min`;

    return `${hours} h`;
  }
  if (milliseconds >= 60000) {
    const minutes = Math.floor(milliseconds / 60000);
    const seconds = Math.round((milliseconds - minutes * 60000) / 1000);
    if (seconds > 0) return `${minutes} min ${seconds} s`;

    return `${minutes} min`;
  }
  if (milliseconds >= 1000) return `${decimal(milliseconds / 1000)} s`;
  if (milliseconds >= 1) return `${decimal(milliseconds)} ms`;
  if (milliseconds === 0) return '0 µs';

  const microseconds = milliseconds * 1000;
  if (microseconds >= 10) return `${microseconds.toFixed(0)} µs`;

  return `${microseconds.toFixed(1)} µs`;
}

function decimal(value) {
  return value.toFixed(2).replace(/\.?0+$/, '');
}

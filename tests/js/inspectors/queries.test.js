import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import {
  createQueriesInspector,
  matchQuery,
  compareQueryDuration,
  compareQueries,
  formatQueryType,
  queryRowTone,
  readQueryRow,
  readCsrfToken,
  syncQueryRowTone,
  QUERIES_EXPLAIN_URL,
} from '../../../webroot/js/inspectors/queries.js';

function fakeRow(attrs = {}) {
  const store = { ...attrs };
  const row = {
    hidden: false,
    parentElement: null,
    getAttribute: (name) => (name in store ? store[name] : null),
    setAttribute: (name, value) => {
      store[name] = String(value);
      if (name === 'hidden') row.hidden = true;
    },
    removeAttribute: (name) => {
      delete store[name];
      if (name === 'hidden') row.hidden = false;
    },
  };

  return row;
}

function fakeRoot(rows = [], extras = {}) {
  const order = [...rows];
  const selectors = {
    '[data-dk-query-item]': () => [...order],
    ...(extras.lists ?? {}),
  };
  return {
    order,
    querySelector: (selector) => {
      if (selector in selectors) return selectors[selector]()[0] ?? null;
      return extras.singles?.[selector] ?? null;
    },
    querySelectorAll: (selector) => {
      if (selector in selectors) return selectors[selector]();

      return extras.lists?.[selector] ?? [];
    },
  };
}

describe('queries pure helpers', () => {
  it('exposes the explain endpoint contract', () => {
    assert.equal(QUERIES_EXPLAIN_URL, '/debug-bar/panels/explain.json');
  });

  it('matches records by needle, type and attention', () => {
    const record = { type: 'read', attention: true, search: 'select users id' };
    assert.equal(matchQuery(record, {}), true);
    assert.equal(matchQuery(record, { needle: 'USERS' }), true);
    assert.equal(matchQuery(record, { needle: 'orders' }), false);
    assert.equal(matchQuery(record, { filter: 'read' }), true);
    assert.equal(matchQuery(record, { filter: 'write' }), false);
    assert.equal(matchQuery(record, { filter: 'attention' }), true);
    assert.equal(
      matchQuery(
        { type: 'write', attention: false, search: '' },
        { filter: 'attention' },
      ),
      false,
    );
  });

  it('sorts by duration with an execution tiebreak', () => {
    const rows = [
      { duration: 5, execution: 2 },
      { duration: 5, execution: 1 },
      { duration: 9, execution: 3 },
    ];
    const asc = [...rows].sort((a, b) => compareQueryDuration(a, b, 1));
    assert.deepEqual(
      asc.map((row) => row.execution),
      [1, 2, 3],
    );
    const desc = [...rows].sort((a, b) => compareQueryDuration(a, b, -1));
    assert.deepEqual(
      desc.map((row) => row.execution),
      [3, 1, 2],
    );
  });

  it('compares rows by sort mode', () => {
    const left = { duration: 5, execution: 2 };
    const right = { duration: 9, execution: 1 };
    assert.equal(compareQueries(left, right, 'execution', 'asc') > 0, true);
    assert.equal(compareQueries(left, right, 'duration', 'desc') > 0, true);
    assert.equal(compareQueries(left, right, 'duration', 'asc') < 0, true);
  });

  it('formats query type labels', () => {
    assert.equal(formatQueryType('read'), 'Read');
    assert.equal(formatQueryType('write'), 'Write');
    assert.equal(formatQueryType(''), 'Query');
    assert.equal(formatQueryType(null), 'Query');
  });

  it('reads row attributes defensively', () => {
    assert.equal(readQueryRow(null), null);
    assert.deepEqual(readQueryRow({ getAttribute: () => null }), {
      key: '',
      execution: 0,
      duration: 0,
      type: '',
      attention: false,
      slow: false,
      repeated: false,
      count: 1,
      search: '',
    });
  });

  it('finds no csrf token outside a browser', () => {
    assert.equal(readCsrfToken(), null);
  });
});

describe('queries inspector state', () => {
  function twoRows() {
    const slow = fakeRow({
      'data-dk-query-key': 'query-1',
      'data-dk-execution': '1',
      'data-dk-duration': '250',
      'data-dk-query-type': 'read',
      'data-dk-attention': 'true',
      'data-dk-slow': 'true',
      'data-dk-repeated': 'false',
      'data-dk-search-text': 'select users',
      'aria-pressed': 'false',
    });
    const plain = fakeRow({
      'data-dk-query-key': 'query-2',
      'data-dk-execution': '2',
      'data-dk-duration': '5',
      'data-dk-query-type': 'write',
      'data-dk-attention': 'false',
      'data-dk-slow': 'false',
      'data-dk-repeated': 'false',
      'data-dk-search-text': 'update users',
      'aria-pressed': 'false',
    });
    const appended = [];
    const parent = { append: (store) => appended.push(store) };
    slow.parentElement = parent;
    plain.parentElement = parent;

    return { slow, plain, appended };
  }

  it('selects records and toggles details', () => {
    const { slow, plain } = twoRows();
    const detail = {
      hidden: true,
      getAttribute: (name) =>
        name === 'data-dk-query-detail' ? 'query-1' : null,
      setAttribute: (name) => {
        detail.hidden = name === 'hidden';
      },
      removeAttribute: () => {
        detail.hidden = false;
      },
      querySelector: () => null,
      querySelectorAll: () => [],
    };
    const root = fakeRoot([slow, plain], {
      lists: { '[data-dk-query-detail]': () => [detail] },
    });
    const inspector = createQueriesInspector(root, {
      fetchClient: async () => null,
    });

    inspector.selectRecord('query-1');
    assert.equal(slow.getAttribute('aria-pressed'), 'true');
    assert.equal(plain.getAttribute('aria-pressed'), 'false');
    assert.equal(detail.hidden, false);

    inspector.selectRecord(null);
    assert.equal(detail.hidden, true);
  });

  it('filters by search, group and sort direction', () => {
    const { slow, plain, appended } = twoRows();
    const root = fakeRoot([slow, plain]);
    const inspector = createQueriesInspector(root, {
      fetchClient: async () => null,
    });

    inspector.onSearch('update');
    assert.equal(slow.hidden, true);
    assert.equal(plain.hidden, false);

    inspector.onSearch('');
    inspector.onFilter('attention');
    assert.equal(slow.hidden, false);
    assert.equal(plain.hidden, true);

    inspector.onFilter('all');
    appended.length = 0;
    inspector.onSortToggle();
    assert.equal(inspector.state.sort, 'duration');
    assert.equal(inspector.state.sortDirection, 'desc');
    assert.deepEqual(appended.length, 2);
    assert.equal(plain.hidden, false);

    inspector.onSortToggle();
    assert.equal(inspector.state.sortDirection, 'asc');
    inspector.onSortToggle();
    assert.equal(inspector.state.sort, 'execution');
  });

  it('counts visible executions, not rows', () => {
    const grouped = fakeRow({
      'data-dk-query-key': 'group-abc',
      'data-dk-execution': '1',
      'data-dk-duration': '10',
      'data-dk-query-type': 'read',
      'data-dk-attention': 'true',
      'data-dk-slow': 'false',
      'data-dk-repeated': 'true',
      'data-dk-query-execution-count': '8',
      'data-dk-search-text': 'select bookings',
      'aria-pressed': 'false',
    });
    const solo = fakeRow({
      'data-dk-query-key': 'query-2',
      'data-dk-execution': '2',
      'data-dk-duration': '3',
      'data-dk-query-type': 'write',
      'data-dk-attention': 'false',
      'data-dk-slow': 'false',
      'data-dk-repeated': 'false',
      'data-dk-search-text': 'update users',
      'aria-pressed': 'false',
    });
    const counter = {
      textContent: '',
      hidden: true,
      setAttribute: (name) => {
        if (name === 'hidden') counter.hidden = true;
      },
      removeAttribute: () => {
        counter.hidden = false;
      },
    };
    const root = fakeRoot([grouped, solo], {
      singles: { '[data-dk-visible-count]': counter },
    });
    const inspector = createQueriesInspector(root, {
      fetchClient: async () => null,
    });
    inspector.onSearch('bookings');
    assert.equal(grouped.hidden, false);
    assert.equal(solo.hidden, true);
    assert.equal(counter.textContent, '8 shown');
    assert.equal(counter.hidden, false);
  });

  it('syncs the sort heading state', () => {
    const { slow, plain } = twoRows();
    const attrs = {};
    const heading = {
      setAttribute: (name, value) => {
        attrs[name] = value;
      },
      querySelector: () => null,
      classList: { toggle: () => {} },
    };
    const root = fakeRoot([slow, plain], {
      singles: { '[data-dk-sort]': heading },
    });
    const inspector = createQueriesInspector(root, {
      fetchClient: async () => null,
    });
    inspector.onSortToggle();
    assert.equal(attrs['aria-pressed'], 'true');
    inspector.onSortToggle();
    assert.equal(attrs['aria-pressed'], 'true');
    inspector.onSortToggle();
    assert.equal(attrs['aria-pressed'], 'false');
  });

  it('paints through the injected highlight hook', () => {
    const { slow } = twoRows();
    const painted = [];
    const root = fakeRoot([slow], {
      lists: { '[data-dk-query-detail]': () => [] },
    });
    const inspector = createQueriesInspector(root, {
      fetchClient: async () => null,
      highlight: (node) => painted.push(node ?? null),
    });
    inspector.selectRecord('query-1');
    assert.equal(painted.length, 1);
  });

  it('buckets row tones by slow and repeated flags', () => {
    assert.equal(queryRowTone({ slow: true, repeated: true }), 'slow');
    assert.equal(queryRowTone({ slow: false, repeated: true }), 'repeated');
    assert.equal(queryRowTone({}), 'default');
  });

  it('swaps selected and idle tone classes on selection', () => {
    const classes = new Set(['dk:hover:bg-zinc-50/80']);
    const row = fakeRow({
      'data-dk-query-key': 'query-9',
      'data-dk-slow': 'true',
      'data-dk-repeated': 'false',
    });
    row.classList = {
      add: (name) => classes.add(name),
      remove: (name) => classes.delete(name),
      contains: (name) => classes.has(name),
      toggle: () => {},
    };
    syncQueryRowTone(row, true);
    assert.equal(classes.has('dk:bg-red-50/90'), true);
    assert.equal(classes.has('dk:hover:bg-zinc-50/80'), false);
    syncQueryRowTone(row, false);
    assert.equal(classes.has('dk:bg-red-50/90'), false);
    assert.equal(classes.has('dk:bg-red-50/70'), true);
  });

  it('leaves rows without a class list alone', () => {
    const row = fakeRow({ 'data-dk-query-key': 'query-9' });
    syncQueryRowTone(row, true);
    assert.equal(row.getAttribute('aria-pressed') ?? null, null);
  });

  it('runs the explain state machine', async () => {
    const plan = [{ detail: 'SCAN' }];
    const fetchOk = async () => ({
      json: async () => ({ plan, mode: 'query-plan', driver: 'Sqlite' }),
    });
    const queue = [];
    const detail = {
      querySelector: (selector) => {
        if (selector === '[data-dk-select]') return null;
        if (selector === '[data-dk-query-execution]') return null;
        const box = {
          hidden: true,
          textContent: '',
          removeAttribute: () => {
            box.hidden = false;
          },
          setAttribute: () => {
            box.hidden = true;
          },
        };
        queue.push([selector, box]);

        return box;
      },
      querySelectorAll: () => [],
    };
    const root = fakeRoot([]);
    const inspector = createQueriesInspector(root, {
      fetchClient: fetchOk,
      requestId: 'req-1',
    });
    const result = await inspector.runExplain(detail);
    assert.deepEqual(result.plan, plan);

    const failing = createQueriesInspector(root, {
      fetchClient: async () => ({
        json: async () => ({ unavailable_reason: 'nope' }),
      }),
      requestId: 'req-1',
    });
    const denied = await failing.runExplain(detail);
    assert.equal(denied.unavailable_reason, 'nope');

    const broken = createQueriesInspector(root, {
      fetchClient: async () => {
        throw new Error('down');
      },
      requestId: 'req-1',
    });
    assert.equal(await broken.runExplain(detail), null);

    const noclient = createQueriesInspector(root, {
      fetchClient: null,
      requestId: 'req-1',
    });
    assert.equal(await noclient.runExplain(detail), null);
  });

  it('renders pretty text plans without the json language', async () => {
    const plan = [
      { 'QUERY PLAN': 'Hash Join' },
      { 'QUERY PLAN': '  -> Seq Scan' },
    ];
    const code = {
      textContent: '',
      language: 'json',
      highlighted: true,
      removeAttribute: (name) => {
        if (name === 'data-dk-language') code.language = null;
        if (name === 'data-highlighted') code.highlighted = false;
      },
      setAttribute: () => {},
    };
    const detail = {
      querySelector: (selector) => {
        if (selector === '[data-dk-select]') return null;
        if (selector === '[data-dk-query-execution]') return null;
        const box = {
          hidden: true,
          textContent: '',
          removeAttribute: () => {
            box.hidden = false;
          },
          setAttribute: () => {
            box.hidden = true;
          },
          querySelector: (inner) =>
            inner === '[data-dk-query-explain-plan-code]' ? code : null,
        };

        return box;
      },
      querySelectorAll: () => [],
    };
    const root = fakeRoot([]);
    const inspector = createQueriesInspector(root, {
      fetchClient: async () => ({
        json: async () => ({ plan, pretty: 'Hash Join\n  -> Seq Scan' }),
      }),
      requestId: 'req-1',
    });
    const result = await inspector.runExplain(detail);
    assert.deepEqual(result.plan, plan);
    assert.equal(code.textContent, 'Hash Join\n  -> Seq Scan');
    assert.equal(code.language, null);
    assert.equal(code.highlighted, false);
  });

  it('keeps the explain pane intact across re-runs', async () => {
    const code = {
      textContent: '',
      language: 'json',
      removeAttribute: (name) => {
        if (name === 'data-dk-language') code.language = null;
      },
      setAttribute: (name, value) => {
        if (name === 'data-dk-language') code.language = value;
      },
    };
    const meta = {
      hidden: true,
      removeAttribute: () => {
        meta.hidden = false;
      },
    };
    const resultBox = {
      hidden: true,
      textContent: '',
      removeAttribute: () => {
        resultBox.hidden = false;
      },
      setAttribute: () => {
        resultBox.hidden = true;
      },
      querySelector: (inner) => {
        if (inner === '[data-dk-query-explain-plan-code]') return code;
        if (inner === '[data-dk-query-explain-meta]') return meta;

        return null;
      },
    };
    const detail = {
      querySelector: (selector) => {
        if (selector === '[data-dk-query-explain-result]') return resultBox;
        if (selector === '[data-dk-select]') return null;
        if (selector === '[data-dk-query-execution]') return null;
        const box = {
          hidden: true,
          textContent: '',
          removeAttribute: () => {
            box.hidden = false;
          },
          setAttribute: () => {
            box.hidden = true;
          },
          querySelector: () => null,
        };

        return box;
      },
      querySelectorAll: () => [],
    };
    const root = fakeRoot([]);
    const inspector = createQueriesInspector(root, {
      fetchClient: async () => ({
        json: async () => ({
          plan: [{ 'QUERY PLAN': 'Aggregate' }],
          pretty: 'Aggregate\n  -> Seq Scan',
          mode: 'explain',
          driver: 'Postgres',
        }),
      }),
      requestId: 'req-1',
    });
    await inspector.runExplain(detail);
    await inspector.runExplain(detail);
    // The pane structure survives: pretty text lands in the code block,
    // never as raw pane text, on every run.
    assert.equal(code.textContent, 'Aggregate\n  -> Seq Scan');
    assert.equal(resultBox.textContent, '');
    assert.equal(resultBox.hidden, false);
  });

  it('resolves the request id lazily at explain time', async () => {
    let current = null;
    const seen = [];
    const fetchOk = async (url, options) => {
      seen.push(JSON.parse(options.body));
      return {
        status: 200,
        json: async () => ({ plan: [], mode: 'explain', driver: 'Mysql' }),
      };
    };
    const detail = {
      querySelector: (selector) => {
        if (selector === '[data-dk-query-execution]') {
          return { getAttribute: () => '4' };
        }
        const box = {
          hidden: true,
          textContent: '',
          removeAttribute: () => {
            box.hidden = false;
          },
          setAttribute: () => {
            box.hidden = true;
          },
          querySelector: () => null,
        };

        return box;
      },
      querySelectorAll: () => [],
    };
    const root = fakeRoot([]);
    const inspector = createQueriesInspector(root, {
      fetchClient: fetchOk,
      requestId: null,
      getRequestId: () => current,
    });

    assert.equal(await inspector.runExplain(detail), null);
    assert.deepEqual(seen, []);

    current = 'req-9';
    const result = await inspector.runExplain(detail);
    assert.deepEqual(result.plan, []);
    assert.deepEqual(seen, [{ request_id: 'req-9', execution: 4 }]);
  });

  it('reports failed HTTP statuses', async () => {
    let message = '';
    const detail = {
      querySelector: (selector) => {
        if (selector === '[data-dk-query-explain-error-text]') {
          return {
            set textContent(value) {
              message = value;
            },
            get textContent() {
              return message;
            },
          };
        }
        const box = {
          hidden: true,
          textContent: '',
          removeAttribute: () => {
            box.hidden = false;
          },
          setAttribute: () => {
            box.hidden = true;
          },
        };

        return box;
      },
      querySelectorAll: () => [],
    };
    const root = fakeRoot([]);
    const inspector = createQueriesInspector(root, {
      fetchClient: async () => ({
        status: 403,
        json: async () => {
          throw new Error('no json');
        },
      }),
      requestId: 'req-1',
    });
    assert.equal(await inspector.runExplain(detail), null);
    assert.equal(message, 'EXPLAIN request failed (HTTP 403).');
  });
});

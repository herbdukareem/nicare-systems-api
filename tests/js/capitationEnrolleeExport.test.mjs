import test from 'node:test'
import assert from 'node:assert/strict'
import { buildCapitationEnrolleeCsv } from '../../resources/js/utils/capitationEnrolleeExport.js'

const batch = (overrides = {}) => ({
  csv: '\uFEFFName\nFirst\n', row_count: 1, next_cursor: 10,
  snapshot_max_id: 30, has_more: true, filename: 'enrollees.csv', ...overrides,
})

test('combines batches into one CSV, freezes the cutoff, and reports processed rows', async () => {
  const calls = []
  const progress = []
  const result = await buildCapitationEnrolleeCsv({
    fetchChunk: async (params) => {
      calls.push(params)
      return calls.length === 1 ? batch() : batch({ csv: 'Second\n', next_cursor: 30, has_more: false })
    },
    onProgress: (count) => progress.push(count),
  })
  assert.equal(Buffer.from(await result.blob.arrayBuffer()).toString('utf8'), '\uFEFFName\nFirst\nSecond\n')
  assert.equal(result.count, 2)
  assert.equal(result.filename, 'enrollees.csv')
  assert.deepEqual(progress, [1, 2])
  assert.deepEqual(calls.map(({ after_id, snapshot_max_id }) => [after_id, snapshot_max_id]), [[0, undefined], [10, 30]])
})

test('retries a failed cursor without duplicating previously downloaded CSV rows', async () => {
  let secondAttempts = 0
  const result = await buildCapitationEnrolleeCsv({
    fetchChunk: async ({ after_id }) => {
      if (after_id === 0) return batch()
      secondAttempts += 1
      if (secondAttempts === 1) throw { response: { status: 504 } }
      return batch({ csv: 'Second\n', next_cursor: 30, has_more: false })
    },
  })
  assert.equal(secondAttempts, 2)
  assert.equal(result.count, 2)
  assert.equal(Buffer.from(await result.blob.arrayBuffer()).toString('utf8'), '\uFEFFName\nFirst\nSecond\n')
})

test('does not retry permission errors or save a partial export', async () => {
  let calls = 0
  const denied = { response: { status: 403, data: { message: 'Forbidden' } } }
  await assert.rejects(buildCapitationEnrolleeCsv({ fetchChunk: async () => { calls += 1; throw denied } }), (err) => err === denied)
  assert.equal(calls, 1)
})

test('cancellation stops the next batch and discards the partial file', async () => {
  const controller = new AbortController()
  let calls = 0
  await assert.rejects(buildCapitationEnrolleeCsv({
    signal: controller.signal,
    fetchChunk: async () => { calls += 1; return batch() },
    onProgress: () => controller.abort(),
  }), { name: 'AbortError' })
  assert.equal(calls, 1)
})

test('rejects stalled cursors instead of repeatedly downloading the same batch', async () => {
  await assert.rejects(buildCapitationEnrolleeCsv({
    fetchChunk: async () => batch({ next_cursor: 0 }),
  }), /invalid batch/)
})

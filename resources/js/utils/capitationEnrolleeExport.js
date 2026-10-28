const abortError = () => new DOMException('Export cancelled.', 'AbortError')

const checkCancelled = (signal) => {
  if (signal?.aborted) throw abortError()
}

const retryDelay = (milliseconds, signal) => new Promise((resolve, reject) => {
  checkCancelled(signal)
  const abort = () => {
    clearTimeout(timer)
    reject(abortError())
  }
  const timer = setTimeout(() => {
    signal?.removeEventListener('abort', abort)
    resolve()
  }, milliseconds)
  signal?.addEventListener('abort', abort, { once: true })
})

const fetchWithRetry = async (fetchChunk, params, signal) => {
  for (let attempt = 0; attempt < 3; attempt += 1) {
    checkCancelled(signal)
    try {
      return await fetchChunk(params, signal)
    } catch (err) {
      checkCancelled(signal)
      const status = err?.response?.status
      const transient = !status || status === 408 || status === 429 || status >= 500
      if (!transient || attempt === 2) throw err
      // Retrying the same cursor is read-only; append only after a full response.
      await retryDelay(500 * (attempt + 1), signal)
    }
  }
}

export const buildCapitationEnrolleeCsv = async ({ fetchChunk, signal, onProgress = () => {} }) => {
  const parts = []
  let cursor = 0
  let maxId
  let count = 0
  let filename

  while (true) {
    const chunk = await fetchWithRetry(fetchChunk, {
      after_id: cursor,
      snapshot_max_id: maxId,
      chunk_size: 2000,
    }, signal)
    checkCancelled(signal)

    if (typeof chunk?.csv !== 'string'
      || !Number.isSafeInteger(chunk.row_count) || chunk.row_count < 0
      || !Number.isSafeInteger(chunk.next_cursor) || chunk.next_cursor < cursor
      || !Number.isSafeInteger(chunk.snapshot_max_id) || chunk.snapshot_max_id < chunk.next_cursor
      || (maxId !== undefined && chunk.snapshot_max_id !== maxId)
      || typeof chunk.has_more !== 'boolean'
      || (chunk.has_more && (chunk.next_cursor <= cursor || chunk.row_count === 0))) {
      throw new Error('The export returned an invalid batch. Please try again.')
    }

    maxId = chunk.snapshot_max_id
    filename ||= chunk.filename
    // Blob parts avoid retaining a large array of enrollee objects or repeatedly
    // copying the complete CSV string while downloading subsequent batches.
    parts.push(new Blob([chunk.csv]))
    count += chunk.row_count
    cursor = chunk.next_cursor
    onProgress(count)
    if (!chunk.has_more) break
  }

  return { blob: new Blob(parts, { type: 'text/csv;charset=utf-8' }), filename, count }
}

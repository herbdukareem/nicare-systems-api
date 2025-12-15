// src/utils/utils.js
import { getCurrentInstance } from 'vue'

/** Title-case a string safely */
function toTitleCase(str = '') {
  return String(str)
    .toLowerCase()
    .replace(/\b\w/g, c => c.toUpperCase())
}

/** Join name parts with options */
function formatName(
  obj = {},
  {
    order = ['first_name', 'middle_name', 'last_name'], // or any order you like
    middleAsInitial = false,
    fallback = '—',
    trimExtraSpaces = true,
  } = {}
) {
  if (!obj || typeof obj !== 'object') return fallback

  const parts = order
    .map((key) => {
      let v = obj[key]
      if (!v) return ''
      v = String(v).trim()
      if (!v) return ''
      if (key.includes('middle') && middleAsInitial) {
        return toTitleCase(v).charAt(0) ? `${toTitleCase(v).charAt(0)}.` : ''
      }
      return toTitleCase(v)
    })
    .filter(Boolean)

  if (!parts.length) return fallback
  const joined = parts.join(' ')
  return trimExtraSpaces ? joined.replace(/\s+/g, ' ').trim() : joined
}

/** Format currency with Intl */
function formatCurrency(
  value,
  {
    currency = 'NGN',
    locale = 'en-NG',
    minimumFractionDigits = 2,
    maximumFractionDigits = 2,
    fallback = '₦0.00',
  } = {}
) {
  const num = typeof value === 'string' ? Number(value) : value
  if (Number.isNaN(num) || num == null) return fallback
  try {
    return new Intl.NumberFormat(locale, {
      style: 'currency',
      currency,
      minimumFractionDigits,
      maximumFractionDigits,
    }).format(num)
  } catch {
    // Fallback if Intl barks
    return `${currency} ${Number(num).toFixed(minimumFractionDigits)}`
  }
}

/** Format date with presets (short/medium/long/relative) */
function formatDate(
  input,
  {
    locale = 'en-NG',
    timeZone, // e.g. 'Africa/Lagos'
    preset = 'medium', // 'short' | 'medium' | 'long' | 'relative'
    fallback = '—',
  } = {}
) {
  if (!input) return fallback
  const date = input instanceof Date ? input : new Date(input)
  if (isNaN(date.getTime())) return fallback

  if (preset === 'relative') {
    try {
      const rtf = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' })
      const now = new Date()
      const diffMs = date - now
      const seconds = Math.round(diffMs / 1000)
      const minutes = Math.round(seconds / 60)
      const hours = Math.round(minutes / 60)
      const days = Math.round(hours / 24)

      if (Math.abs(seconds) < 60) return rtf.format(seconds, 'second')
      if (Math.abs(minutes) < 60) return rtf.format(minutes, 'minute')
      if (Math.abs(hours) < 24) return rtf.format(hours, 'hour')
      return rtf.format(days, 'day')
    } catch {
      // fall through to date format
    }
  }

  const presets = {
    short: { year: '2-digit', month: '2-digit', day: '2-digit' },
    medium: { year: 'numeric', month: 'short', day: '2-digit' },
    long: { year: 'numeric', month: 'long', day: '2-digit' },
  }
  const opts = presets[preset] || presets.medium
  if (timeZone) opts.timeZone = timeZone

  try {
    return new Intl.DateTimeFormat(locale, opts).format(date)
  } catch {
    return date.toISOString().slice(0, 10) // YYYY-MM-DD
  }
}


const getStatusColor = (status) => {
  if (!status) return 'grey';
  switch (status.toLowerCase()) {
    case 'active':
    case 'approved':
    case 'paid':
      return 'success';
    case 'pending':
      return 'warning';
    case 'expired':
    case 'inactive':
    case 'rejected':
      return 'error';
    default:
      return 'grey';
  }
};

/** Composable: import and use in <script setup> */
export function useUtils() {
  // if you ever want the same object instance app-provided, you can inject here
  return { formatName, formatCurrency, formatDate, getStatusColor }
}

/** Plugin: exposes $utils in templates and Options API */
export default {
  install(app) {
    const utils = { formatName, formatCurrency, formatDate, getStatusColor }
    app.config.globalProperties.$utils = utils
    // also provide for inject() if you like
    app.provide('utils', utils)
  }
}

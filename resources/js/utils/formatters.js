/**
 * Formatter utilities for common formatting tasks
 */

/**
 * Format a price/currency value
 * @param {number|string} value - The value to format
 * @param {string} currency - Currency code (default: NGN)
 * @param {string} locale - Locale for formatting (default: en-NG)
 * @returns {string} Formatted currency string
 */
export function formatPrice(value, currency = 'NGN', locale = 'en-NG') {
  const num = typeof value === 'string' ? Number(value) : value
  
  if (Number.isNaN(num) || num == null) {
    return '₦0.00'
  }
  
  try {
    return new Intl.NumberFormat(locale, {
      style: 'currency',
      currency,
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(num)
  } catch {
    // Fallback if Intl fails
    return `₦${Number(num).toFixed(2)}`
  }
}

/**
 * Format a number as currency without currency symbol
 * @param {number|string} value - The value to format
 * @returns {string} Formatted number string
 */
export function formatNumber(value) {
  const num = typeof value === 'string' ? Number(value) : value
  
  if (Number.isNaN(num) || num == null) {
    return '0.00'
  }
  
  try {
    return new Intl.NumberFormat('en-NG', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(num)
  } catch {
    return Number(num).toFixed(2)
  }
}

/**
 * Format a percentage value
 * @param {number|string} value - The value to format (0-100)
 * @param {number} decimals - Number of decimal places
 * @returns {string} Formatted percentage string
 */
export function formatPercent(value, decimals = 2) {
  const num = typeof value === 'string' ? Number(value) : value
  
  if (Number.isNaN(num) || num == null) {
    return '0%'
  }
  
  return `${Number(num).toFixed(decimals)}%`
}

/**
 * Format a phone number
 * @param {string} phone - The phone number to format
 * @returns {string} Formatted phone number
 */
export function formatPhone(phone) {
  if (!phone) return ''
  
  const cleaned = String(phone).replace(/\D/g, '')
  
  if (cleaned.length === 10) {
    return `(${cleaned.slice(0, 3)}) ${cleaned.slice(3, 6)}-${cleaned.slice(6)}`
  } else if (cleaned.length === 11) {
    return `+${cleaned.slice(0, 1)} (${cleaned.slice(1, 4)}) ${cleaned.slice(4, 7)}-${cleaned.slice(7)}`
  }
  
  return phone
}

/**
 * Format a date string
 * @param {string|Date} date - The date to format
 * @param {string} format - Format preset: 'short', 'medium', 'long'
 * @returns {string} Formatted date string
 */
export function formatDate(date, format = 'medium') {
  if (!date) return ''
  
  const dateObj = date instanceof Date ? date : new Date(date)
  
  if (isNaN(dateObj.getTime())) {
    return ''
  }
  
  const presets = {
    short: { year: '2-digit', month: '2-digit', day: '2-digit' },
    medium: { year: 'numeric', month: 'short', day: '2-digit' },
    long: { year: 'numeric', month: 'long', day: '2-digit' },
  }
  
  const opts = presets[format] || presets.medium
  
  try {
    return new Intl.DateTimeFormat('en-NG', opts).format(dateObj)
  } catch {
    return dateObj.toISOString().slice(0, 10)
  }
}

/**
 * Format a time string
 * @param {string|Date} time - The time to format
 * @returns {string} Formatted time string (HH:MM:SS)
 */
export function formatTime(time) {
  if (!time) return ''
  
  const dateObj = time instanceof Date ? time : new Date(time)
  
  if (isNaN(dateObj.getTime())) {
    return ''
  }
  
  try {
    return new Intl.DateTimeFormat('en-NG', {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hour12: false,
    }).format(dateObj)
  } catch {
    return dateObj.toTimeString().slice(0, 8)
  }
}

/**
 * Format a datetime string
 * @param {string|Date} datetime - The datetime to format
 * @returns {string} Formatted datetime string
 */
export function formatDateTime(datetime) {
  if (!datetime) return ''
  
  const dateObj = datetime instanceof Date ? datetime : new Date(datetime)
  
  if (isNaN(dateObj.getTime())) {
    return ''
  }
  
  try {
    const date = new Intl.DateTimeFormat('en-NG', {
      year: 'numeric',
      month: 'short',
      day: '2-digit',
    }).format(dateObj)
    
    const time = new Intl.DateTimeFormat('en-NG', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(dateObj)
    
    return `${date} ${time}`
  } catch {
    return dateObj.toISOString()
  }
}

/**
 * Format a file size
 * @param {number} bytes - The file size in bytes
 * @returns {string} Formatted file size
 */
export function formatFileSize(bytes) {
  if (!bytes || bytes === 0) return '0 B'
  
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  
  return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i]
}

/**
 * Format a status badge
 * @param {string} status - The status value
 * @returns {object} Object with color and label
 */
export function formatStatus(status) {
  const statusMap = {
    active: { color: 'success', label: 'Active' },
    inactive: { color: 'error', label: 'Inactive' },
    pending: { color: 'warning', label: 'Pending' },
    approved: { color: 'success', label: 'Approved' },
    rejected: { color: 'error', label: 'Rejected' },
    draft: { color: 'info', label: 'Draft' },
    submitted: { color: 'info', label: 'Submitted' },
    expired: { color: 'error', label: 'Expired' },
    used: { color: 'success', label: 'Used' },
    cancelled: { color: 'error', label: 'Cancelled' },
  }
  
  return statusMap[status?.toLowerCase()] || { color: 'grey', label: status || 'Unknown' }
}

export default {
  formatPrice,
  formatNumber,
  formatPercent,
  formatPhone,
  formatDate,
  formatTime,
  formatDateTime,
  formatFileSize,
  formatStatus,
}


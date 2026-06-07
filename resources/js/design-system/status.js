const STATUS_META = {
  active: { tone: 'success', icon: 'mdi-check-circle-outline', label: 'Active' },
  inactive: { tone: 'neutral', icon: 'mdi-minus-circle-outline', label: 'Inactive' },
  pending: { tone: 'warning', icon: 'mdi-clock-outline', label: 'Pending' },
  approved: { tone: 'success', icon: 'mdi-check-decagram', label: 'Approved' },
  rejected: { tone: 'danger', icon: 'mdi-close-circle-outline', label: 'Rejected' },
  denied: { tone: 'danger', icon: 'mdi-cancel', label: 'Denied' },
  draft: { tone: 'neutral', icon: 'mdi-file-document-edit-outline', label: 'Draft' },
  submitted: { tone: 'info', icon: 'mdi-upload-outline', label: 'Submitted' },
  paid: { tone: 'success', icon: 'mdi-cash-check', label: 'Paid' },
  unpaid: { tone: 'warning', icon: 'mdi-cash-remove', label: 'Unpaid' },
  expired: { tone: 'danger', icon: 'mdi-calendar-remove-outline', label: 'Expired' },
  suspended: { tone: 'danger', icon: 'mdi-pause-circle-outline', label: 'Suspended' },
  reviewing: { tone: 'secondary', icon: 'mdi-eye-outline', label: 'Reviewing' },
  review: { tone: 'secondary', icon: 'mdi-eye-outline', label: 'In Review' },
  processing: { tone: 'secondary', icon: 'mdi-cog-outline', label: 'Processing' },
  queried: { tone: 'warning', icon: 'mdi-comment-alert-outline', label: 'Queried' },
  generated: { tone: 'info', icon: 'mdi-calculator-variant-outline', label: 'Generated' },
  finalised: { tone: 'success', icon: 'mdi-check-all', label: 'Finalised' },
  in_progress: { tone: 'secondary', icon: 'mdi-progress-clock', label: 'In Progress' },
  admitted: { tone: 'info', icon: 'mdi-bed-outline', label: 'Admitted' },
  discharged: { tone: 'success', icon: 'mdi-bed-empty', label: 'Discharged' },
  validated: { tone: 'success', icon: 'mdi-shield-check-outline', label: 'Validated' },
  invalid: { tone: 'danger', icon: 'mdi-shield-off-outline', label: 'Invalid' },
  primary: { tone: 'primary', icon: 'mdi-hospital-box-outline', label: 'Primary' },
  secondary_facility: { tone: 'secondary', icon: 'mdi-hospital-building', label: 'Secondary' },
  tertiary: { tone: 'info', icon: 'mdi-domain', label: 'Tertiary' },
}

export const normalizeStatusKey = (value) => String(value ?? '')
  .trim()
  .toLowerCase()
  .replace(/[\s-]+/g, '_')

export const formatStatusLabel = (value) => String(value ?? '')
  .replace(/_/g, ' ')
  .replace(/\b\w/g, (letter) => letter.toUpperCase())

export const getStatusMeta = (value, fallback = {}) => {
  const key = normalizeStatusKey(value)
  return {
    tone: 'neutral',
    icon: 'mdi-circle-medium',
    label: formatStatusLabel(value || 'Unknown'),
    ...STATUS_META[key],
    ...fallback,
  }
}

export const getFacilityTypeMeta = (value) => {
  const key = normalizeStatusKey(value)

  if (key === 'secondary') {
    return STATUS_META.secondary_facility
  }

  return getStatusMeta(value, { tone: 'neutral', icon: 'mdi-hospital-box-outline' })
}

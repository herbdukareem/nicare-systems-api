const planDuration = (plan) => {
  if (plan?.has_no_expiry) return 'No expiry after activation'

  const days = Number(plan?.duration_days || 0)
  return days > 0 ? `${days} day${days === 1 ? '' : 's'} after activation` : 'Pending activation'
}

export const coverageStartDisplay = (enrollee, formatDate) => {
  if (enrollee?.coverage_start_date) return formatDate(enrollee.coverage_start_date)
  return Number(enrollee?.status) === 0 ? 'Pending approval' : 'Not started'
}

export const coverageEndDisplay = (enrollee, formatDate) => {
  if (enrollee?.coverage_end_date) return formatDate(enrollee.coverage_end_date)

  if (!enrollee?.coverage_start_date) {
    return planDuration(enrollee?.premium_plan)
  }

  if (enrollee?.is_no_expiry || enrollee?.premium_plan?.has_no_expiry) {
    return 'No Expiry'
  }

  return 'End date pending'
}

export const coverageSummaryDisplay = (enrollee, formatDate) => {
  if (!enrollee?.coverage_start_date) {
    const duration = planDuration(enrollee?.premium_plan)
    return duration === 'Pending activation' ? duration : `Pending activation · ${duration}`
  }

  const start = formatDate(enrollee.coverage_start_date)
  return `${start} → ${coverageEndDisplay(enrollee, formatDate)}`
}

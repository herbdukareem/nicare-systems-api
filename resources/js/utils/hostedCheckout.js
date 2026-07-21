const popupFeatures = 'width=520,height=760,noopener,noreferrer'

const openWindow = (targetName) => window.open('', targetName, popupFeatures)

const submitCheckoutForm = (checkoutForm, targetName) => {
  if (!checkoutForm?.action) {
    return false
  }

  const form = document.createElement('form')
  form.method = (checkoutForm.method || 'POST').toUpperCase()
  form.action = checkoutForm.action

  const popup = openWindow(targetName)
  form.target = popup ? targetName : '_self'
  form.style.display = 'none'

  Object.entries(checkoutForm.fields || {}).forEach(([name, value]) => {
    if (value === null || value === undefined) {
      return
    }

    const input = document.createElement('input')
    input.type = 'hidden'
    input.name = name
    input.value = String(value)
    form.appendChild(input)
  })

  document.body.appendChild(form)
  form.submit()
  form.remove()

  return true
}

export const openHostedCheckout = (checkout, targetName = 'hosted-checkout') => {
  if (!checkout) {
    return false
  }

  if (checkout.checkout_form?.action) {
    return submitCheckoutForm(checkout.checkout_form, targetName)
  }

  const authorizationUrl = checkout.authorization_url || checkout.url
  if (!authorizationUrl) {
    return false
  }

  const popup = window.open(authorizationUrl, targetName, popupFeatures)
  if (!popup) {
    window.location.href = authorizationUrl
  }

  return true
}

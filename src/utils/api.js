export const apiFetch = async (url, options = {}) => {
  const response = await fetch(url, {
    credentials: 'same-origin',
    ...options,
    headers: {
      ...(options.headers || {})
    }
  })

  if (response.status === 401) {
    window.location.replace('/')
    throw new Error('Authentication required')
  }

  return response
}

export const checkSession = async () => {
  try {
    const response = await fetch('/api/check_session.php', {
      credentials: 'same-origin'
    })

    if (!response.ok) {
      return false
    }

    const data = await response.json()
    return Boolean(data.authenticated)
  } catch (error) {
    return false
  }
}

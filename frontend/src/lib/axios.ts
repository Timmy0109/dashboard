import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

const PUBLIC_PATHS = ['/login', '/register']

api.interceptors.response.use(
  res => res,
  err => {
    const isPublic = PUBLIC_PATHS.some(p => window.location.pathname.startsWith(p))
    if (err.response?.status === 401 && !isPublic) {
      window.location.href = '/login'
    }
    return Promise.reject(err)
  }
)

export default api

import Swal from 'sweetalert2'
import 'sweetalert2/dist/sweetalert2.min.css'

const baseOptions = {
  confirmButtonColor: '#14532d',
  cancelButtonColor: '#6c757d'
}

export const alertSuccess = (title, text = '') => Swal.fire({
  ...baseOptions,
  icon: 'success',
  title,
  text
})

export const alertError = (title, text = '') => Swal.fire({
  ...baseOptions,
  confirmButtonColor: '#dc3545',
  icon: 'error',
  title,
  text
})

export const alertWarning = (title, text = '') => Swal.fire({
  ...baseOptions,
  confirmButtonColor: '#d97706',
  icon: 'warning',
  title,
  text
})

export const confirmWarning = ({
  title = 'Are you sure?',
  text = '',
  confirmButtonText = 'Yes, continue',
  cancelButtonText = 'Cancel'
} = {}) => Swal.fire({
  ...baseOptions,
  confirmButtonColor: '#dc3545',
  icon: 'warning',
  title,
  text,
  showCancelButton: true,
  confirmButtonText,
  cancelButtonText
})

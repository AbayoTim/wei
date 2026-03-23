// WEI API Helper Functions

const API_BASE = '/api';

// Generic API call function
async function apiCall(endpoint, method = 'GET', data = null, isFormData = false) {
  const options = {
    method,
    headers: {}
  };

  // Add auth token if available
  const token = localStorage.getItem('wei_admin_token');
  if (token) {
    options.headers['Authorization'] = `Bearer ${token}`;
  }

  if (data) {
    if (isFormData) {
      options.body = data;
    } else {
      options.headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(data);
    }
  }

  try {
    const response = await fetch(`${API_BASE}${endpoint}`, options);
    return await response.json();
  } catch (error) {
    console.error('API Error:', error);
    return { success: false, message: 'Network error. Please try again.' };
  }
}

// Newsletter subscription
async function subscribeNewsletter(email, name = null) {
  return apiCall('/subscribers/subscribe', 'POST', { email, name });
}

// Contact form submission
async function submitContactForm(formData) {
  return apiCall('/contact', 'POST', formData);
}

// Donation submission
async function submitDonation(formData) {
  return apiCall('/donations', 'POST', formData, true);
}

// Get blogs
async function getBlogs(page = 1, limit = 10, category = null) {
  let url = `/blogs?page=${page}&limit=${limit}`;
  if (category) url += `&category=${category}`;
  return apiCall(url);
}

// Get single blog
async function getBlog(slug) {
  return apiCall(`/blogs/${slug}`);
}

// Get causes/programs
async function getCauses(page = 1, limit = 10, category = null) {
  let url = `/content/causes?page=${page}&limit=${limit}`;
  if (category) url += `&category=${category}`;
  return apiCall(url);
}

// Get events
async function getEvents(upcoming = true) {
  return apiCall(`/content/events?upcoming=${upcoming}`);
}

// Get team members
async function getTeamMembers() {
  return apiCall('/content/team');
}

// Get partners
async function getPartners() {
  return apiCall('/partners');
}

// Get site content
async function getSiteContent() {
  return apiCall('/content/site');
}

// Get approved donations for display
async function getApprovedDonations(limit = 10) {
  return apiCall(`/donations/approved?limit=${limit}`);
}

// Format currency
function formatCurrency(amount, currency = 'TZS') {
  return `${currency} ${Number(amount).toLocaleString()}`;
}

// Format date
function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  });
}

// Show toast notification
function showToast(message, type = 'success') {
  const toast = document.createElement('div');
  toast.className = `alert alert-${type} position-fixed`;
  toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
  toast.textContent = message;
  document.body.appendChild(toast);

  setTimeout(() => {
    toast.remove();
  }, 3000);
}

import '../css/app.css'

// TreeHouse Framework integration
console.log('TreeHouse + Vite + Tailwind CSS loaded successfully!')

// Initialize TreeHouse when available
if (typeof TreeHouse !== 'undefined') {
  TreeHouse.ready(() => {
    console.log('TreeHouse Framework is ready!')
    
    // Add any custom initialization here
    initializeComponents()
  })
} else {
  // Fallback for when TreeHouse isn't loaded yet
  document.addEventListener('DOMContentLoaded', () => {
    initializeComponents()
  })
}

function initializeComponents() {
  // Add smooth scrolling to navigation links
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
      e.preventDefault()
      
      const target = document.querySelector(this.getAttribute('href'))
      if (target) {
        target.scrollIntoView({
          behavior: 'smooth'
        })
      }
    })
  })
  
  // Add fade-in animation to cards
  const cards = document.querySelectorAll('.treehouse-card')
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('animate-fade-in')
      }
    })
  })
  
  cards.forEach(card => {
    observer.observe(card)
  })
  
  // Mobile menu toggle
  window.toggleMobileMenu = function() {
    const menu = document.getElementById('mobile-menu')
    if (menu) {
      menu.classList.toggle('hidden')
    }
  }
  
  // Console welcome message
  console.log(`
  🌳 TreeHouse Framework
  
  A modern PHP framework with elegant JavaScript integration
  Version: 1.0.0
  `)
}
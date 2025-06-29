import '../css/app.css'

// Import TreeHouse framework (available globally)
import '../../assets/js/treehouse.js'

// Import TreeHouse modules
import '../../assets/js/modules/csrf.js'

// Initialize TreeHouse when ready
TreeHouse.ready(() => {
  // Add any custom initialization here
  initializeComponents()

  console.log('TreeHouse: All components initialized');
})

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
}
// Enhanced Hover Effects
document.addEventListener('DOMContentLoaded', function() {
    
    // 3D Card Tilt Effect
    const cards = document.querySelectorAll('.course-card, .stat-card, .certificate-card');
    
    cards.forEach(card => {
        card.addEventListener('mousemove', function(e) {
            const rect = this.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            
            const rotateX = (y - centerY) / 20;
            const rotateY = (centerX - x) / 20;
            
            this.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-5px)`;
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) translateY(0)';
        });
    });
    
    // Ripple Effect on Buttons
    const buttons = document.querySelectorAll('.btn-login, .btn-enroll, .btn-action');
    
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            ripple.classList.add('ripple');
            
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = e.clientX - rect.left - size/2 + 'px';
            ripple.style.top = e.clientY - rect.top - size/2 + 'px';
            
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
    
    // Add ripple style dynamically
    const style = document.createElement('style');
    style.textContent = `
        .btn-login, .btn-enroll, .btn-action {
            position: relative;
            overflow: hidden;
        }
        
        .ripple {
            position: absolute;
            background: rgba(255,255,255,0.5);
            border-radius: 50%;
            transform: scale(0);
            animation: ripple-animation 0.6s ease-out;
            pointer-events: none;
        }
        
        @keyframes ripple-animation {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
    
    // Floating Animation for Icons
    const icons = document.querySelectorAll('.stat-icon i, .logo i');
    
    icons.forEach(icon => {
        setInterval(() => {
            icon.style.transform = `translateY(${Math.sin(Date.now() / 500) * 5}px)`;
        }, 50);
    });
    
    // Glow Effect on Hover for Images
    const images = document.querySelectorAll('.course-image img, .profile-img');
    
    images.forEach(img => {
        img.addEventListener('mouseenter', function() {
            this.style.filter = 'brightness(1.1) contrast(1.1)';
            this.style.boxShadow = '0 0 20px rgba(67,97,238,0.5)';
        });
        
        img.addEventListener('mouseleave', function() {
            this.style.filter = 'none';
            this.style.boxShadow = 'none';
        });
    });
    
    // Animated Counter for Numbers
    const statNumbers = document.querySelectorAll('.stat-number');
    
    function animateNumber(element, start, end, duration) {
        const range = end - start;
        const increment = range / (duration / 16);
        let current = start;
        
        const timer = setInterval(() => {
            current += increment;
            element.textContent = Math.floor(current).toLocaleString();
            
            if (current >= end) {
                element.textContent = end.toLocaleString();
                clearInterval(timer);
            }
        }, 16);
    }
    
    // Observer for number animation
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const element = entry.target;
                const finalValue = parseInt(element.textContent.replace(/,/g, ''));
                
                if (!isNaN(finalValue) && finalValue > 0) {
                    animateNumber(element, 0, finalValue, 2000);
                }
                
                observer.unobserve(element);
            }
        });
    }, { threshold: 0.5 });
    
    statNumbers.forEach(num => observer.observe(num));
    
    // Parallax Effect on Login Page
    const loginWrapper = document.querySelector('.login-wrapper');
    if (loginWrapper) {
        window.addEventListener('mousemove', function(e) {
            const x = e.clientX / window.innerWidth;
            const y = e.clientY / window.innerHeight;
            
            loginWrapper.style.backgroundPosition = `${x * 20}px ${y * 20}px`;
        });
    }
    
    // Smooth Page Transitions
    document.body.style.opacity = '0';
    document.body.style.transition = 'opacity 0.5s ease';
    
    window.addEventListener('load', () => {
        document.body.style.opacity = '1';
    });
    
    // Link transitions
    const links = document.querySelectorAll('a:not([target="_blank"])');
    
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            
            if (href && !href.startsWith('#') && !href.startsWith('javascript')) {
                e.preventDefault();
                
                document.body.style.opacity = '0';
                
                setTimeout(() => {
                    window.location.href = href;
                }, 500);
            }
        });
    });
    
    // Form validation effects
    const formInputs = document.querySelectorAll('.form-control');
    
    formInputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.querySelector('i')?.style.setProperty('color', 'var(--primary)');
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.querySelector('i')?.style.setProperty('color', 'var(--gray)');
        });
    });
    
    // Notification badge pulse
    const badges = document.querySelectorAll('.badge');
    
    setInterval(() => {
        badges.forEach(badge => {
            badge.style.transform = 'scale(1.2)';
            setTimeout(() => {
                badge.style.transform = 'scale(1)';
            }, 200);
        });
    }, 3000);
});
document.getElementById('menuToggle').addEventListener('click', function() {
    document.querySelector('.sidebar').classList.toggle('active');
    document.querySelector('.sidebar-overlay').classList.toggle('active');
});

document.querySelector('.sidebar-overlay').addEventListener('click', function() {
    document.querySelector('.sidebar').classList.remove('active');
    this.classList.remove('active');
});
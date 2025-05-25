// Main JavaScript functionality for Furniture Bidding System

document.addEventListener('DOMContentLoaded', function() {
    // Load featured items on the homepage
    if (document.getElementById('featured-items')) {
        loadFeaturedItems();
    }

    // Initialize bid timers
    initializeBidTimers();

    // Setup bid form handlers
    setupBidForms();
});

// Load featured furniture items
function loadFeaturedItems() {
    fetch('api/get_featured_items.php')
        .then(response => response.json())
        .then(items => {
            const container = document.getElementById('featured-items');
            items.forEach(item => {
                container.appendChild(createItemCard(item));
            });
        })
        .catch(error => console.error('Error loading featured items:', error));
}

// Create a card element for a furniture item
function createItemCard(item) {
    const card = document.createElement('div');
    card.className = 'col-md-4 col-sm-6';
    card.innerHTML = `
        <div class="card furniture-card">
            <img src="${item.image_url}" class="card-img-top" alt="${item.title}">
            <div class="card-body">
                <h5 class="card-title">${item.title}</h5>
                <p class="card-text">${item.description}</p>
                <div class="bid-info">
                    <p class="current-price">Current Bid: $${item.current_price}</p>
                    <p class="bid-timer" data-end="${item.end_time}">Time left: Calculating...</p>
                </div>
                <a href="item.php?id=${item.item_id}" class="btn btn-primary">View Details</a>
            </div>
        </div>
    `;
    return card;
}

// Initialize countdown timers for bids
function initializeBidTimers() {
    const timers = document.querySelectorAll('.bid-timer');
    timers.forEach(timer => {
        updateTimer(timer);
        setInterval(() => updateTimer(timer), 1000);
    });
}

// Update individual bid timer
function updateTimer(timerElement) {
    const endTime = new Date(timerElement.dataset.end).getTime();
    const now = new Date().getTime();
    const distance = endTime - now;

    if (distance < 0) {
        timerElement.innerHTML = 'Auction ended';
        return;
    }

    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((distance % (1000 * 60)) / 1000);

    timerElement.innerHTML = `${days}d ${hours}h ${minutes}m ${seconds}s`;
}

// Setup bid form submission handlers
function setupBidForms() {
    document.querySelectorAll('.bid-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('api/place_bid.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Bid placed successfully!');
                    // Update the current price display
                    const priceElement = form.closest('.card').querySelector('.current-price');
                    priceElement.textContent = `Current Bid: $${data.new_price}`;
                } else {
                    alert(data.message || 'Error placing bid');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error placing bid');
            });
        });
    });
}

// Form validation helper
function validateForm(formElement) {
    const inputs = formElement.querySelectorAll('input[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            isValid = false;
            input.classList.add('is-invalid');
        } else {
            input.classList.remove('is-invalid');
        }
    });
    
    return isValid;
} 
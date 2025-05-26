function deleteBid(bidId) {
    if (confirm('Are you sure you want to delete this bid?')) {
        fetch('delete_bid.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'bid_id=' + bidId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                showAlert('Bid deleted successfully!', 'success');
                // Reload the page after a short delay
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showAlert(data.error || 'Error deleting bid', 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error deleting bid', 'danger');
        });
    }
}

function editBid(bidId, currentAmount) {
    const newAmount = prompt('Enter new bid amount:', currentAmount);
    
    if (newAmount !== null) {
        const amount = parseFloat(newAmount);
        
        if (isNaN(amount) || amount <= 0) {
            showAlert('danger', 'Please enter a valid amount.');
            return;
        }
        
        $.ajax({
            url: 'manage_bid.php',
            type: 'POST',
            data: {
                action: 'edit',
                bid_id: bidId,
                new_amount: amount
            },
            success: function(response) {
                if (response.success) {
                    // Update both the bid amount and current price displays
                    const card = $(`#bid-${bidId}`);
                    card.find('.text-primary').text('₱' + amount.toFixed(2));
                    card.find('.text-success').text('₱' + amount.toFixed(2));
                    
                    // Update the bid status to show as highest bidder
                    const bidStatus = card.find('.bid-status');
                    bidStatus.html(`
                        <span class="badge bg-success">
                            <i class="fas fa-trophy"></i> Highest Bidder
                        </span>
                    `);
                    
                    showAlert('success', response.message);
                } else {
                    showAlert('danger', response.message);
                }
            },
            error: function() {
                showAlert('danger', 'Error processing your request.');
            }
        });
    }
}

function showAlert(message, type) {
    const alertContainer = document.getElementById('alert-container');
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    alertContainer.appendChild(alertDiv);
    
    // Remove the alert after 3 seconds
    setTimeout(() => {
        alertDiv.remove();
    }, 3000);
} 
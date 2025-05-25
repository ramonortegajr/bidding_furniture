function deleteBid(bidId) {
    if (confirm('Are you sure you want to delete this bid?')) {
        $.ajax({
            url: 'manage_bid.php',
            type: 'POST',
            data: {
                action: 'delete',
                bid_id: bidId
            },
            success: function(response) {
                if (response.success) {
                    // Remove the bid row from the table
                    $(`#bid-${bidId}`).fadeOut(400, function() {
                        $(this).remove();
                    });
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

function showAlert(type, message) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    
    $('#alert-container').html(alertHtml);
    
    // Auto-hide alert after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut(400, function() {
            $(this).remove();
        });
    }, 5000);
} 
# Furniture Bidding System

A web-based platform for furniture bidding built with PHP, MySQL, HTML, CSS, and JavaScript. The system features a real-time chat system for buyer-seller communication, bidding functionality, and a responsive user interface.

## Features
- User Registration and Authentication
- Furniture Listing Management
- Real-time Bidding System
- Real-time Chat System
  - Direct messaging between buyers and sellers
  - Real-time message notifications
  - Read receipts
  - Typing indicators
  - Automatic message updates
- Admin Dashboard
- Responsive Design with Bootstrap 5
- Image Upload and Management

## Requirements
- PHP 8.0 or higher
- MySQL 5.7 or higher
- Web Server (Apache/Nginx)
- Modern Web Browser
- XAMPP (recommended) or similar PHP development environment

## Installation
1. Clone the repository
2. Import the database schema from `database/schema.sql`
3. Configure database connection in `config/database.php`
4. Start your web server
5. Access the application through your web browser

## Directory Structure
```
furniture_bidding_system/
├── assets/           # CSS, JS, images
│   ├── css/         # Custom CSS files
│   ├── js/          # Custom JavaScript files
│   └── images/      # System images and icons
├── config/          # Configuration files
│   └── database.php # Database connection settings
├── database/        # Database schema
├── includes/        # PHP helper functions and common components
│   └── navigation_common.php # Common navigation elements
├── admin/           # Admin panel files
├── api/            # API endpoints
│   └── chat_status.php # Chat status management
├── uploads/         # Furniture images
└── vendor/          # Dependencies
```

## Key Features Breakdown

### Chat System
- Real-time messaging between buyers and sellers
- Conversation management per furniture item
- Message read status tracking
- Real-time typing indicators
- Automatic message updates (every 5 seconds)
- Message history with timestamps
- Responsive chat interface

### User Interface
- Bootstrap 5 framework
- Font Awesome icons
- Mobile-responsive design
- Real-time updates
- Clean and intuitive navigation

### Security Features
- Session-based authentication
- Prepared SQL statements
- Input validation and sanitization
- Secure file upload handling

## External Dependencies
- Bootstrap 5.1.3
- Font Awesome 6.0.0
- jQuery (for AJAX functionality)

## Browser Support
- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)

## Contributing
Contributions are welcome! Please feel free to submit a Pull Request. 
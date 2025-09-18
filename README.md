# TechMart Backend (Laravel API)

A comprehensive e-commerce backend API built with Laravel.

## 🚀 Quick Start

### Prerequisites
- PHP 8.1+
- Composer
- MySQL 8.0+
- Node.js (for asset compilation)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/YahampathChandika/TechMart_BE.git
   cd TechMart_BE
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configure database**
   Update `.env` file with your database credentials:
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=techmart
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

5. **Run migrations and seed data**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

6. **Start the server**
   ```bash
   php artisan serve
   ```

API will be available at `http://localhost:8000`

## 🔑 Default Credentials

**Admin Account:**
- Email: `admin@techmart.com`
- Password: `password123`

**Test Customer:**
- Email: `customer@techmart.com`
- Password: `password123`

## 📊 API Endpoints

### Authentication
- `POST /api/auth/login` - Admin/User login
- `POST /api/auth/register` - User registration
- `POST /api/customer/login` - Customer login
- `POST /api/customer/register` - Customer registration

### Products
- `GET /api/products` - Get all products (public)
- `GET /api/admin/products` - Admin product management
- `POST /api/admin/products` - Create product
- `PUT /api/admin/products/{id}` - Update product
- `DELETE /api/admin/products/{id}` - Delete product

### Cart
- `GET /api/cart` - Get cart items
- `POST /api/cart/add` - Add to cart
- `PUT /api/cart/update/{id}` - Update cart item
- `DELETE /api/cart/remove/{id}` - Remove from cart

## 🛠 Features

- JWT Authentication for Admin/Users and Customers
- Product Management (CRUD operations)
- User Management with Role-based Permissions
- Customer Management
- Shopping Cart System
- Image Upload for Products
- Search and Filtering
- Dashboard Statistics

## 📁 Project Structure

```
app/
├── Http/Controllers/Api/     # API Controllers
├── Models/                   # Eloquent Models
├── Http/Requests/           # Form Requests
└── Http/Resources/          # API Resources

database/
├── migrations/              # Database Migrations
└── seeders/                # Database Seeders

routes/
└── api.php                 # API Routes
```

## 🧪 Testing

Use the provided Postman collection for API testing:
- Import the collection from the repository
- Set environment variables (base_url, tokens)
- Run tests for all endpoints

---

**Developed by:** Yahampath Chandika  
**Email:** yhmpth@gmail.com

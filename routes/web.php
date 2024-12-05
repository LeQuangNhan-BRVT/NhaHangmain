<?php
use App\Http\Controllers\admin\AdminLoginController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\admin\HomeController;
use App\Http\Controllers\admin\CategoryController;
use App\Http\Controllers\admin\MenuController;
use App\Http\Controllers\FrontController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\Admin\AdminBookingController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminManagementController;
use App\Http\Controllers\UserMenuController;




// Front-end Routes
Route::get('/', [FrontController::class, 'index'])->name('front.home');
Route::get('/about', [FrontController::class, 'about'])->name('front.about');
Route::get('/contact', [FrontController::class, 'contact'])->name('front.contact');
Route::get('/service', [FrontController::class, 'service'])->name('front.service');
Route::get('/menu', [FrontController::class, 'menu'])->name('front.menu');
Route::get('/team', [FrontController::class, 'team'])->name('front.team');
Route::get('/testimonial', [FrontController::class, 'testimonial'])->name('front.testimonial');
Route::get('/menu/{id}', [UserMenuController::class, 'detail'])->name('front.menu.detail');

// Booking Routes (Public)
Route::get('/booking', [BookingController::class, 'create'])->name('front.booking');
Route::post('/booking', [BookingController::class, 'store'])->name('front.booking.store');
Route::get('/booking/success', [BookingController::class, 'success'])->name('front.booking.success');
Route::get('/booking/confirm', [BookingController::class, 'showConfirmation'])->name('front.confirm');
Route::post('/booking/process-payment', [BookingController::class, 'processPayment'])->name('front.booking.process-payment');

// User Auth Routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return redirect()->route('front.home');
    })->name('dashboard');
    
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // Booking history routes (Protected)
    Route::prefix('bookings')->name('bookings.')->group(function () {
        Route::get('/', [BookingController::class, 'index'])->name('index');
        Route::post('/{booking}/cancel', [BookingController::class, 'cancel'])->name('cancel');
        Route::get('/{booking}/edit', [BookingController::class, 'edit'])->name('edit');
        Route::put('/{booking}', [BookingController::class, 'update'])->name('update');
        Route::put('/{booking}/cancel', [BookingController::class, 'cancel'])->name('cancel');
    });
});


// Admin Routes
Route::prefix('admin')->group(function () {
    Route::get('/login', [AdminLoginController::class, 'index'])->name('admin.login');
    Route::post('/admin/login', [AdminLoginController::class, 'authenticate'])->name('admin.authenticate');
    
    Route::group(['middleware' => 'auth:admin'], function () {
        Route::get('/dashboard', [HomeController::class, 'index'])->name('admin.dashboard');
        Route::get('/logout', [HomeController::class, 'logout'])->name('admin.logout');
        
        //category_route
        Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::resource('categories', CategoryController::class);
        
        //menu_route
        Route::get('/menu/create', [MenuController::class, 'create'])->name('menu.create');
        Route::post('/menu', [MenuController::class, 'store'])->name('menu.store');
        Route::resource('menu', MenuController::class);
        Route::post('menu/update-position', [MenuController::class, 'updatePosition'])->name('menu.update-position');
        //booking_route
        Route::get('/bookings', [AdminBookingController::class, 'index'])->name('admin.bookings.index');
        Route::get('/bookings/{id}', [AdminBookingController::class, 'show'])->name('admin.bookings.show');
        Route::post('/bookings/{id}/status', [AdminBookingController::class, 'updateStatus'])->name('admin.bookings.updateStatus');
        Route::delete('/bookings/{id}', [AdminBookingController::class, 'destroy'])->name('admin.bookings.destroy');
        // Users management (role = 0)
        Route::resource('users', AdminUserController::class, ['as' => 'admin']);
    
        // Admins management (role = 1)
        Route::get('/admins', [AdminManagementController::class, 'index'])->name('admin.admins.index');
        Route::get('/admins/create', [AdminManagementController::class, 'create'])->name('admin.admins.create');
        Route::post('/admins', [AdminManagementController::class, 'store'])->name('admin.admins.store');
        Route::get('/admins/{id}/edit', [AdminManagementController::class, 'edit'])->name('admin.admins.edit');
        Route::put('/admins/{id}', [AdminManagementController::class, 'update'])->name('admin.admins.update');
        Route::delete('/admins/{id}', [AdminManagementController::class, 'destroy'])->name('admin.admins.destroy');

        Route::get('/settings', [AdminLoginController::class, 'settings'])->name('admin.settings');
        Route::post('/settings', [AdminLoginController::class, 'updateSettings'])->name('admin.updateSettings');
        Route::get('/change-password', [AdminLoginController::class, 'changePassword'])->name('admin.changePassword');
        Route::post('/change-password', [AdminLoginController::class, 'updatePassword'])->name('admin.updatePassword');
    });
    
    Route::get('/create', [AdminLoginController::class, 'create'])->name('admin.create');
    Route::post('/store', [AdminLoginController::class, 'store'])->name('admin.store');
    Route::get('/forgot-password', [AdminLoginController::class, 'forgotPassword'])->name('admin.forgotPassword');
    Route::post('/forgot-password', [AdminLoginController::class, 'forgotPasswordProcess'])->name('admin.forgotPasswordProcess');
    Route::get('/reset-password/{token}', [AdminLoginController::class, 'resetPassword'])->name('admin.resetPassword');
    Route::post('/reset-password', [AdminLoginController::class, 'resetPasswordProcess'])->name('admin.resetPasswordProcess');
});

// Authentication Routes
require __DIR__.'/auth.php';
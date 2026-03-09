<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ContactSettingController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\CustomerCareController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LandingBannerController;
use App\Http\Controllers\menuController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OtpAuthController;
use App\Http\Controllers\PaymentGatewayController;
use App\Http\Controllers\phonepaycontroller;
use App\Http\Controllers\posController;
use App\Http\Controllers\ProductBulkImportController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImageController;
use App\Http\Controllers\ProductSeoMetaController;
use App\Http\Controllers\ProductTaxAffinityController;
use App\Http\Controllers\ProductVariantController;
use App\Http\Controllers\ProductVariationController;
use App\Http\Controllers\ProductVariationValueController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ShippingController;
use App\Http\Controllers\ShiprocketController;
use App\Http\Controllers\StaffAttendanceController;
use App\Http\Controllers\StaffUserController;
use App\Http\Controllers\WhatsAppController;
use App\Http\Controllers\WhatsappSettingController;
use App\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Route;

Route::post('/payment/create', [phonepaycontroller::class, 'create']);

Route::get('/payment/status/{transactionId}',
    [phonepaycontroller::class, 'status']);

Route::post('/webhook', [CartController::class, 'razorpayWebhook']);


Route::get('/test-whatsapp', function(App\Services\Messenger360Service $messenger){

    $response = $messenger->send(
        "918919273834",
        "Test message from Laravel"
    );

    return $response;
});

Route::prefix('whatsapp')->group(function () {
    Route::post('/webhook', [WhatsAppController::class, 'webhook']);

    Route::post('/payment', [CartController::class, 'createPaymentLink']);

});

Route::prefix('auth')->group(function () {

    // ================= AUTH =================
    // Route::get('sample', [AuthController::class, 'sample']);

    // Route::get('/stats', [DashboardController::class, 'stats']);

    // Login with OTP / Register
    Route::post('/send-otp', [OtpAuthController::class, 'sendOtp']);
    Route::post('/verify-login-otp', [OtpAuthController::class, 'verifyOtp']);

    Route::post('super-admin-login', [AuthController::class, 'super_admin_login']);
    Route::post('admin-register', [AuthController::class, 'admin_register']);
    Route::post('admin-login', [AuthController::class, 'admin_login']);
    Route::post('user-register', [AuthController::class, 'register']);
    Route::post('user-login', [AuthController::class, 'login']);

    // ================= PASSWORD / OTP =================
    Route::post('forgot-password', [AuthController::class, 'sendOtp']);
    Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);

    Route::get('app-logo-settings', [SettingController::class, 'show']);

    Route::post('organization/forgot-password', [AuthController::class, 'OrgsendOtp']);

    Route::get(
        '/razorpay-key',
        [CartController::class, 'razorpayKey']
    );

    Route::get('/message', [OrderController::class, 'sendWhatsappTest']);

});

Route::prefix('admin-dashboard')->middleware(['api', 'jwt.auth'])->group(function () {

    // ================= AUTH =================
    Route::get('/user-details', [AuthController::class, 'userDetails']);
    Route::get('/user-order-details', [posController::class, 'userDetails']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::post('/update-profile', [AuthController::class, 'update']);
    Route::get('/app-logo-settings', [SettingController::class, 'show']);
    Route::post('/app-logo-settings', [SettingController::class, 'update']);

    //-------------------STAFFF-----------------

    Route::get('/staff', [StaffUserController::class, 'index']);
    Route::post('/add-staff', [StaffUserController::class, 'store']);
    Route::get('/staff/{id}', [StaffUserController::class, 'show']);
    Route::put('/update-staff/{id}', [StaffUserController::class, 'update']);
    Route::delete('/delete-staff/{id}', [StaffUserController::class, 'destroy']);

    //===================== SOCIAL MEDIA SETTINGS =====================
    Route::get('/social-media-settings', [SettingController::class, 'show_social_media']);
    Route::post('/social-media-settings', [SettingController::class, 'store_social_media']);

    //===================== PAYMENT GATEWAY SETTINGS =====================

    // Route::get('/payment-gateways', [PaymentGatewayController::class, 'show']);
    Route::get('/payment-gateways', [PaymentGatewayController::class, 'index']);
    Route::post('/payment-gateways', [PaymentGatewayController::class, 'store']);
    Route::delete('/payment-gateways', [PaymentGatewayController::class, 'destroy']);

    // Product Variations
    Route::get('/get-variations', [ProductVariationController::class, 'index']);
    Route::post('/add-variation', [ProductVariationController::class, 'store']);
    Route::put('/update-variations/{id}', [ProductVariationController::class, 'update']);
    Route::delete('/delete-variations/{id}', [ProductVariationController::class, 'destroy']);

    // VARIATION VALUES
    Route::get('/get-variations', [ProductVariationValueController::class, 'index']);
    Route::post('/add-variation-value/{variationId}', [ProductVariationValueController::class, 'store']);
    Route::put('/update-variation-value/{id}', [ProductVariationValueController::class, 'update']);
    Route::delete('/delete-variation-value/{id}', [ProductVariationValueController::class, 'destroy']);

    // Whats App Integration VALUES
    Route::get('/whatsapp-settings', [WhatsappSettingController::class, 'index']);
    Route::post('/whatsapp-settings', [WhatsappSettingController::class, 'store']);

    // Shipping Details adding and fetching
    Route::get('/shipping', [ShippingController::class, 'index']);
    Route::post('/shipping', [ShippingController::class, 'store']);
    Route::get('/enabled-couriers', [ShippingController::class, 'enabledCouriers']);
    Route::post('/send-courier/{id}', [ShippingController::class, 'sendCourier']);

    // Coupon Management
    Route::get('/cart/list-coupon', [CouponController::class, 'index']);
    Route::post('/cart/create-coupon', [CouponController::class, 'store']);
    Route::put('/cart/update-coupon/{id}', [CouponController::class, 'update']);
    Route::delete('/cart/delete-coupon/{id}', [CouponController::class, 'destroy']);

    // Category Management
    Route::get('/list-category', [CategoryController::class, 'index']);
    Route::get('/list-category-all', [CategoryController::class, 'index_all']);
    Route::post('/add-category', [CategoryController::class, 'store']);
    Route::post('/update-category/{id}', [CategoryController::class, 'update']);
    Route::delete('/delete-category/{id}', [CategoryController::class, 'destroy']);
    Route::post(
        '/add-sub-category',
        [CategoryController::class, 'addSubCategory']
    );

    // Brand Management
    Route::get('list-brand', [BrandController::class, 'index']);
    Route::post('add-brand', [BrandController::class, 'store']);
    Route::post('update-brand/{id}', [BrandController::class, 'update']);
    Route::delete('delete-brand/{id}', [BrandController::class, 'destroy']);

    // Product Management
    Route::get('products', [ProductController::class, 'index']);
    Route::get('/pos-products', [ProductController::class, 'posProducts']);
    Route::get('/product/fetch-products-by-id/{id}', [ProductController::class, 'fetchById']);
    Route::post('create-product', [ProductController::class, 'store']);
    Route::post('update-product/{id}', [ProductController::class, 'update']);
    Route::delete('delete-product/{id}', [ProductController::class, 'destroy']);
    Route::get('/pos-products-search', [ProductController::class, 'posProductsSearch']);

    Route::post('/product/bulk-upload', [ProductController::class, 'upload']);

    // Product Gallery Management
    Route::post('product/{product}/gallery', [ProductImageController::class, 'store']);
    Route::post('product/{product}/gallery', [ProductImageController::class, 'update']);

    // update product variations and variant combinations
    Route::post('/product/{product}/images', [ProductImageController::class, 'addImages']);
    Route::delete('/product/image/{image}', [ProductImageController::class, 'deleteImage']);
    Route::post('/product/{product}/set-main-image', [ProductImageController::class, 'setMainImage']);
    Route::post('/product/{product}/videos', [ProductImageController::class, 'updateVideos']);

    // product variant routes
    Route::post('product/create-variation/{product}', [ProductVariantController::class, 'store']);
    Route::post('product/update-variation/{product}', [ProductVariantController::class, 'syncVariations']);

// Product SEO Meta Management

    Route::post('product-seo-meta/{product}', [ProductSeoMetaController::class, 'store']);
    Route::post('product-seo-meta/update-meta/{product}', [ProductSeoMetaController::class, 'update']);

    // product Tax Affinity Management Routes

    Route::post('product-tax-affinity/{product}/', [ProductTaxAffinityController::class, 'store']);
    Route::post('product-tax-affinity/update-tax/{product}', [ProductTaxAffinityController::class, 'update']);

    // publish the product
    Route::post('publish-product/{id}', [ProductController::class, 'publish']);

    Route::get('/orders', [OrderController::class, 'allorders']);
    Route::get('/orders-details/{id}', [OrderController::class, 'allorders_show']);
    Route::post('/orders/{id}/status', [OrderController::class, 'updateStatus']);

    // Attendance

    Route::get('/attendance', [StaffAttendanceController::class, 'getAttendance']);
    Route::post('/attendance', [StaffAttendanceController::class, 'saveAttendance']);

    // contact page setting
    Route::post('contact-setting', [ContactSettingController::class, 'save']);
    Route::get('contact-setting', [ContactSettingController::class, 'show']);

    // landing page contact detiails
    Route::post('customer-care', [CustomerCareController::class, 'save']);
    Route::get('customer-care', [CustomerCareController::class, 'show']);

    // statics

    Route::get('/stats', [DashboardController::class, 'stats']);

    // shiprocket integration not using
    Route::post(
        '/shiprocket/create/{order}',
        [ShiprocketController::class, 'createShipment']);

    // create pos order
    Route::post('/send-order-otp', [PosController::class, 'sendOrderOtp']);
    Route::post('/create-payment-link', [CartController::class, 'createPaymentLink']);
    Route::post('/check-payment-link', [CartController::class, 'checkPaymentLink']);

    // 🟢 Step 2: Verify OTP & Create Final Order
    Route::post('/verify-order-otp', [PosController::class, 'verifyOrderOtp']);
    Route::post('/pos/create-order', [posController::class, 'store']);

    // add customer

    Route::post('/customers/store', [CustomerController::class, 'store']);
    Route::post('/customers/bulk-store', [CustomerController::class, 'bulkStore']);
    Route::get('/pos/search-user', [CustomerController::class, 'searchUser']);
    Route::post('/save-address', [AddressController::class, 'posstore']);

    // pos order details fetch
    Route::get('/calling/orders', [posController::class, 'manualOrders']);
    Route::get('/calling/order/{id}', [posController::class, 'manualOrderDetails']);
    // Route::post('/send-courier/{id}', [posController::class, 'sendToCourier']);
    Route::get('/customer/{id}/orders', [posController::class, 'customerOrders']);

    Route::post('/update-salary', [StaffUserController::class, 'updateSalary']);
    Route::post('/update-staff/{id}', [StaffUserController::class, 'updateStaff']);

    // For Banner Section Dynamic Banner

    Route::get('/banners', [BannerController::class, 'index']);
    Route::post('/banners', [BannerController::class, 'store']);
    Route::get('/banners/{id}', [BannerController::class, 'show']);
    Route::post('/banners/{id}', [BannerController::class, 'update']);
    Route::patch('/banners/{id}/status', [BannerController::class, 'changeStatus']);
    Route::delete('/banners/{id}', [BannerController::class, 'destroy']);

    // Landing page banner
    Route::get('/landing-banners', [LandingBannerController::class, 'adminList']);
    Route::post('/add-banners', [LandingBannerController::class, 'store']);
    Route::post('/update-banners/{id}', [LandingBannerController::class, 'update']);
    Route::delete('/delete-banners/{id}', [LandingBannerController::class, 'destroy']);

    // product adding to sections
    Route::post('/sections', [SectionController::class, 'store']);
    Route::get('/sections', [SectionController::class, 'index']);
    Route::post('/products/{id}/sections', [SectionController::class, 'assignSections']);
    Route::post('/upate-sections/{id}', [SectionController::class, 'update']);

    // bulk upload excel sheet
    Route::post('/products-bulk-import', [ProductBulkImportController::class, 'bulkUpload']);
    Route::get('/products-bulk-import/template', [ProductBulkImportController::class, 'downloadBulkTemplate']);

    Route::get(
        '/product-variants',
        [ProductBulkImportController::class, 'productVariants']
    );
    Route::post(
        '/bulk-product-variant-images',
        [ProductBulkImportController::class, 'bulkProductVariantImages']
    );

    Route::delete(
        '/delete-variant-image/{id}',
        [ProductBulkImportController::class, 'deleteVariantImage']
    );

    Route::delete(
        '/delete-product-image/{id}',
        [ProductBulkImportController::class, 'deleteProductImage']
    );

});

Route::prefix('ecom')->group(function () {
    Route::get('menu', [menuController::class, 'menu']);
    Route::get('products', [menuController::class, 'products']);
    Route::get('products-main', [menuController::class, 'products_main']);

    // app settion globel
    Route::get('/app-logo-settings', [SettingController::class, 'show']);
    Route::get('/list-brand', [BrandController::class, 'index_no_pagination']);

    // customer care details
    Route::post('customer-care', [CustomerCareController::class, 'save']);
    Route::get('customer-care', [CustomerCareController::class, 'show']);

    // contact  details

    Route::get('contact-setting', [ContactSettingController::class, 'show']);

    // social media
    Route::get('/social-media-settings', [SettingController::class, 'show_social_media']);

    Route::get('/active-banner', [BannerController::class, 'activeBanner']);
    // search by slug category wise
    Route::get('/collections', [ProductController::class, 'collection']);
    // landing page banner
    Route::get('/landing-banners', [LandingBannerController::class, 'index']);

    /* Section wise Product Listing */
    Route::get('/sections/{slug}', [SectionController::class, 'productsBySection']);

    Route::get('/home-sections', [SectionController::class, 'homeSections']);

});

Route::prefix('user-dashboard')->middleware(['api', 'jwt.auth'])->group(function () {

    // Cart Functionalities

    Route::post('/cart/sync', [CartController::class, 'sync']);
    Route::get('/cart', [CartController::class, 'get']);
    Route::delete('/cart/clear', [CartController::class, 'clear']);

    // address functionalies

    Route::get('/cart/get-address', [AddressController::class, 'index']);
    Route::post('/cart/add-address', [AddressController::class, 'store']);
    Route::post('/cart/update-address/{id}', [AddressController::class, 'update']);
    Route::delete('/cart/delete-address/{id}', [AddressController::class, 'destroy']);
    Route::post('/cart/set-default-address/{id}', [AddressController::class, 'setDefault']);

    // payent
    Route::post('/cart/create-order', [CartController::class, 'createOrder']);
    Route::post('/cart/verify-payment', [CartController::class, 'verifyPayment']);
    Route::post('/cart/save-order', [CartController::class, 'saveOrder']);

    // coupen check
    Route::post('/cart/apply-coupon', [CouponController::class, 'apply']);

    // order item save
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders  ', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::get('/get-my-orders/{id}', [OrderController::class, 'getMyOrderDetails']);
    Route::delete('/orders/{id}', [OrderController::class, 'destroy']);

    // profile update
    Route::post('/update-profile', [AuthController::class, 'update']);

    // whishlist toggle

    Route::get('/get-wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist-toggle', [WishlistController::class, 'toggle']);

    // Payment Gateways
    Route::get('/list-payment-gateways', [PaymentGatewayController::class, 'index']);

    // // phonepay create-order
    // Route::post('/create-phone-order', [PhonePayController::class, 'create']);

    Route::post('/create-phone-order', [PhonePayController::class, 'create']);
    Route::post('/payment/status', [PhonePayController::class, 'status'])->name('phonepe.status');

});

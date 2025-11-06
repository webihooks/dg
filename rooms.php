<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voxton Hotel - Rooms</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .room-card {
            transition: transform 0.3s ease;
        }
        .room-card:hover {
            transform: translateY(-5px);
        }
        .amenity-icon {
            width: 24px;
            height: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-sm sticky top-0 z-10">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center">
                <i class="fas fa-hotel text-blue-600 text-xl mr-2"></i>
                <h1 class="text-xl font-bold text-gray-800">Voxton Hotel</h1>
            </div>
            <div class="flex items-center space-x-4">
                <button class="text-gray-600">
                    <i class="fas fa-search"></i>
                </button>
                <button class="text-gray-600">
                    <i class="fas fa-user"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <div class="bg-blue-600 text-white py-6">
        <div class="container mx-auto px-4">
            <h2 class="text-2xl font-bold mb-2">Find Your Perfect Stay</h2>
            <p class="text-blue-100">Luxury rooms with premium amenities</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white py-4 border-b">
        <div class="container mx-auto px-4">
            <div class="flex overflow-x-auto space-x-4 pb-2">
                <button class="bg-blue-100 text-blue-700 px-4 py-2 rounded-full whitespace-nowrap flex items-center">
                    <i class="fas fa-filter mr-2"></i> Filters
                </button>
                <button class="bg-gray-100 text-gray-700 px-4 py-2 rounded-full whitespace-nowrap">
                    Price: Low to High
                </button>
                <button class="bg-gray-100 text-gray-700 px-4 py-2 rounded-full whitespace-nowrap">
                    Guest Rating
                </button>
                <button class="bg-gray-100 text-gray-700 px-4 py-2 rounded-full whitespace-nowrap">
                    Popular
                </button>
            </div>
        </div>
    </div>

    <!-- Room Listings -->
    <div class="container mx-auto px-4 py-6">
        <h3 class="text-xl font-bold mb-4">Available Rooms</h3>
        
        <!-- Room 1 -->
        <div class="bg-white rounded-xl shadow-md room-card mb-6 overflow-hidden">
            <div class="relative">
                <img src="https://images.unsplash.com/photo-1611892440504-42a792e24d32?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxzZWFyY2h8Mnx8aG90ZWwlMjByb29tfGVufDB8fDB8fHww&auto=format&fit=crop&w=500&q=60" 
                     alt="Deluxe Room" class="w-full h-48 object-cover">
                <div class="absolute top-3 right-3 bg-white rounded-full px-2 py-1 flex items-center">
                    <i class="fas fa-star text-yellow-400 mr-1"></i>
                    <span class="font-medium">4.5</span>
                </div>
            </div>
            <div class="p-4">
                <div class="flex justify-between items-start mb-2">
                    <h4 class="text-lg font-bold">Deluxe Room</h4>
                    <div class="text-right">
                        <p class="text-xl font-bold text-blue-600">₹2,499</p>
                        <p class="text-xs text-gray-500">per night</p>
                    </div>
                </div>
                <p class="text-gray-600 text-sm mb-3">Spacious room with city view, king-sized bed and modern amenities</p>
                
                <div class="flex flex-wrap gap-2 mb-4">
                    <div class="flex items-center text-sm text-gray-600">
                        <i class="fas fa-user-friends amenity-icon mr-1"></i>
                        <span>2 Adults</span>
                    </div>
                    <div class="flex items-center text-sm text-gray-600">
                        <i class="fas fa-bed amenity-icon mr-1"></i>
                        <span>1 King Bed</span>
                    </div>
                    <div class="flex items-center text-sm text-gray-600">
                        <i class="fas fa-wifi amenity-icon mr-1"></i>
                        <span>Free WiFi</span>
                    </div>
                </div>
                
                <div class="flex justify-between items-center">
                    <button class="text-blue-600 font-medium flex items-center">
                        <i class="fas fa-eye mr-1"></i> View Details
                    </button>
                    <button class="bg-blue-600 text-white px-4 py-2 rounded-lg font-medium">
                        Book Now
                    </button>
                </div>
            </div>
        </div>

        <!-- Room 2 -->
        <div class="bg-white rounded-xl shadow-md room-card mb-6 overflow-hidden">
            <div class="relative">
                <img src="https://images.unsplash.com/photo-1566665797739-1674de7a421a?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxzZWFyY2h8NHx8aG90ZWwlMjByb29tfGVufDB8fDB8fHww&auto=format&fit=crop&w=500&q=60" 
                     alt="Executive Suite" class="w-full h-48 object-cover">
                <div class="absolute top-3 right-3 bg-white rounded-full px-2 py-1 flex items-center">
                    <i class="fas fa-star text-yellow-400 mr-1"></i>
                    <span class="font-medium">4.7</span>
                </div>
            </div>
            <div class="p-4">
                <div class="flex justify-between items-start mb-2">
                    <h4 class="text-lg font-bold">Executive Suite</h4>
                    <div class="text-right">
                        <p class="text-xl font-bold text-blue-600">₹4,299</p>
                        <p class="text-xs text-gray-500">per night</p>
                    </div>
                </div>
                <p class="text-gray-600 text-sm mb-3">Luxurious suite with separate living area and premium amenities</p>
                
                <div class="flex flex-wrap gap-2 mb-4">
                    <div class="flex items-center text-sm text-gray-600">
                        <i class="fas fa-user-friends amenity-icon mr-1"></i>
                        <span>3 Adults</span>
                    </div>
                    <div class="flex items-center text-sm text-gray-600">
                        <i class="fas fa-bed amenity-icon mr-1"></i>
                        <span>1 King + 1 Single</span>
                    </div>
                    <div class="flex items-center text-sm text-gray-600">
                        <i class="fas fa-wifi amenity-icon mr-1"></i>
                        <span>Free WiFi</span>
                    </div>
                    <div class="flex items-center text-sm text-gray-600">
                        <i class="fas fa-tv amenity-icon mr-1"></i>
                        <span>Smart TV</span>
                    </div>
                </div>
                
                <div class="flex justify-between items-center">
                    <button class="text-blue-600 font-medium flex items-center">
                        <i class="fas fa-eye mr-1"></i> View Details
                    </button>
                    <button class="bg-blue-600 text-white px-4 py-2 rounded-lg font-medium">
                        Book Now
                    </button>
                </div>
            </div>
        </div>

        <!-- Room 3 -->
        <div class="bg-white rounded-xl shadow-md room-card mb-6 overflow-hidden">
            <div class="relative">
                <img src="https://images.unsplash.com/photo-1590490360182-c33d57733427?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxzZWFyY2h8MTB8fGhvdGVsJTIwcm9vbXxlbnwwfHwwfHx8MA%3D%3D&auto=format&fit=crop&w=500&q=60" 
                     alt="Standard Room" class="w-full h-48 object-cover">
                <div class="absolute top-3 right-3 bg-white rounded-full px-2 py-1 flex items-center">
                    <i class="fas fa-star text-yellow-400 mr-1"></i>
                    <span class="font-medium">4.2</span>
                </div>
            </div>
            <div class="p-4">
                <div class="flex justify-between items-start mb-2">
                    <h4 class="text-lg font-bold">Standard Room</h4>
                    <div class="text-right">
                        <p class="text-xl font-bold text-blue-600">₹1,799</p>
                        <p class="text-xs text-gray-500">per night</p>
                    </div>
                </div>
                <p class="text-gray-600 text-sm mb-3">Comfortable room with all essential amenities for a pleasant stay</p>
                
                <div class="flex flex-wrap gap-2 mb-4">
                    <div class="flex items-center text-sm text-gray-600">
                        <i class="fas fa-user-friends amenity-icon mr-1"></i>
                        <span>2 Adults</span>
                    </div>
                    <div class="flex items-center text-sm text-gray-600">
                        <i class="fas fa-bed amenity-icon mr-1"></i>
                        <span>1 Queen Bed</span>
                    </div>
                    <div class="flex items-center text-sm text-gray-600">
                        <i class="fas fa-wifi amenity-icon mr-1"></i>
                        <span>Free WiFi</span>
                    </div>
                    <div class="flex items-center text-sm text-gray-600">
                        <i class="fas fa-utensils amenity-icon mr-1"></i>
                        <span>Breakfast</span>
                    </div>
                </div>
                
                <div class="flex justify-between items-center">
                    <button class="text-blue-600 font-medium flex items-center">
                        <i class="fas fa-eye mr-1"></i> View Details
                    </button>
                    <button class="bg-blue-600 text-white px-4 py-2 rounded-lg font-medium">
                        Book Now
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <nav class="bg-white border-t fixed bottom-0 w-full">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-3">
                <a href="#" class="flex flex-col items-center text-blue-600">
                    <i class="fas fa-home text-lg"></i>
                    <span class="text-xs mt-1">Home</span>
                </a>
                <a href="#" class="flex flex-col items-center text-gray-500">
                    <i class="fas fa-search text-lg"></i>
                    <span class="text-xs mt-1">Search</span>
                </a>
                <a href="#" class="flex flex-col items-center text-gray-500">
                    <i class="fas fa-heart text-lg"></i>
                    <span class="text-xs mt-1">Saved</span>
                </a>
                <a href="#" class="flex flex-col items-center text-gray-500">
                    <i class="fas fa-user text-lg"></i>
                    <span class="text-xs mt-1">Profile</span>
                </a>
            </div>
        </div>
    </nav>

    <script>
        // Simple JavaScript for interactive elements
        document.addEventListener('DOMContentLoaded', function() {
            // Add click event to "View Details" buttons
            const viewDetailsButtons = document.querySelectorAll('.room-card button:first-child');
            viewDetailsButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const roomName = this.closest('.room-card').querySelector('h4').textContent;
                    alert(`Details for ${roomName} would be shown here.`);
                });
            });

            // Add click event to "Book Now" buttons
            const bookNowButtons = document.querySelectorAll('.bg-blue-600');
            bookNowButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const roomName = this.closest('.room-card').querySelector('h4').textContent;
                    alert(`Booking process for ${roomName} would start here.`);
                });
            });
        });
    </script>
</body>
</html>
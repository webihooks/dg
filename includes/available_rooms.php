<?php
// available_rooms.php

// Function to get available rooms data (renamed to avoid conflict)
function getAvailableRoomsData($conn, $user_id) {
    $table_name = "rooms_" . $user_id;
    
    // Check if the table exists
    $check_table_sql = "SHOW TABLES LIKE '$table_name'";
    $check_stmt = $conn->prepare($check_table_sql);
    $check_stmt->execute();
    $table_exists = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$table_exists) {
        return [];
    }
    
    // Get room types for joining
    $room_types_table = "room_types_" . $user_id;
    
    // Build the query based on available tables
    $sql = "SELECT r.*, rt.name as room_type_name, rt.description as room_type_description, 
                   rt.max_occupancy, rt.size_sqft, rt.bed_type, rt.amenities as room_type_amenities
            FROM $table_name r 
            LEFT JOIN $room_types_table rt ON r.room_type_id = rt.id 
            WHERE r.is_active = 1 AND r.status = 'available' 
            ORDER BY r.room_number ASC";
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching rooms: " . $e->getMessage());
        return [];
    }
}

// Get available rooms for the current user
$available_rooms = getAvailableRoomsData($conn, $user_id);

// Function to get Unsplash image based on room type
function getRoomUnsplashImage($room_type, $index = 0) {
    $room_images = [
        'standard' => [
            'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=500&h=300&fit=crop',
            'https://images.unsplash.com/photo-1586105251261-72a756497a11?w=500&h=300&fit=crop',
            'https://images.unsplash.com/photo-1566665797739-1674de7a421a?w=500&h=300&fit=crop'
        ],
        'deluxe' => [
            'https://images.unsplash.com/photo-1611892440504-42a792e24d32?w=500&h=300&fit=crop',
            'https://images.unsplash.com/photo-1590490360182-c33d57733427?w=500&h=300&fit=crop',
            'https://images.unsplash.com/photo-1564078516393-cf04bd966897?w=500&h=300&fit=crop'
        ],
        'suite' => [
            'https://images.unsplash.com/photo-1540518614846-7eded1027f2b?w=500&h=300&fit=crop',
            'https://images.unsplash.com/photo-1578683010236-d716f9a3f461?w=500&h=300&fit=crop',
            'https://images.unsplash.com/photo-1595526114035-0d45ed16cfbf?w=500&h=300&fit=crop'
        ],
        'family' => [
            'https://images.unsplash.com/photo-1596394516093-501ba68a0ba6?w=500&h=300&fit=crop',
            'https://images.unsplash.com/photo-1571508601891-ca5e7a713859?w=500&h=300&fit=crop',
            'https://images.unsplash.com/photo-1584132967334-10e028bd69f7?w=500&h=300&fit=crop'
        ],
        'luxury' => [
            'https://images.unsplash.com/photo-1618773928121-c32242e63f39?w=500&h=300&fit=crop',
            'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?w=500&h=300&fit=crop',
            'https://images.unsplash.com/photo-1578683010236-d716f9a3f461?w=500&h=300&fit=crop'
        ]
    ];
    
    // Determine room type category
    $room_type_lower = strtolower($room_type);
    if (strpos($room_type_lower, 'suite') !== false) {
        $category = 'suite';
    } elseif (strpos($room_type_lower, 'deluxe') !== false) {
        $category = 'deluxe';
    } elseif (strpos($room_type_lower, 'family') !== false) {
        $category = 'family';
    } elseif (strpos($room_type_lower, 'luxury') !== false || strpos($room_type_lower, 'premium') !== false) {
        $category = 'luxury';
    } else {
        $category = 'standard';
    }
    
    // Get random image from the category
    $images = $room_images[$category] ?? $room_images['standard'];
    $image_index = $index % count($images);
    
    return $images[$image_index];
}

// Function to format room amenities
function formatRoomAmenities($amenities_json) {
    if (!$amenities_json) return [];
    
    $amenities = json_decode($amenities_json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return explode(',', $amenities_json);
    }
    
    return is_array($amenities) ? $amenities : [];
}

// Function to get amenity icon
function getRoomAmenityIcon($amenity) {
    $icons = [
        'wifi' => 'wifi',
        'tv' => 'tv',
        'ac' => 'snowflake',
        'air conditioning' => 'snowflake',
        'mini bar' => 'glass-cheers',
        'room service' => 'concierge-bell',
        'safe' => 'shield-alt',
        'jacuzzi' => 'hot-tub',
        'balcony' => 'mountain',
        'breakfast' => 'utensils',
        'parking' => 'parking',
        'swimming pool' => 'swimming-pool',
        'gym' => 'dumbbell',
        'spa' => 'spa',
        'laundry' => 'tshirt',
        'restaurant' => 'utensils',
        'bar' => 'cocktail',
        'concierge' => 'concierge-bell',
        'business' => 'briefcase',
        'pet' => 'paw'
    ];
    
    $amenity_lower = strtolower($amenity);
    foreach ($icons as $key => $icon) {
        if (strpos($amenity_lower, $key) !== false) {
            return $icon;
        }
    }
    
    return 'check';
}
?>

<!-- Available Rooms Section -->
<div class="mt-4 px-4">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-bold text-gray-800">Available Rooms</h2>
        <span class="text-sm text-gray-600 bg-gray-100 px-3 py-1 rounded-full">
            <?= count($available_rooms) ?> rooms available
        </span>
    </div>
    
    <?php if (empty($available_rooms)): ?>
    <!-- No Rooms Available State -->
    <div class="bg-white rounded-xl shadow-sm p-8 text-center">
        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-bed text-gray-400 text-2xl"></i>
        </div>
        <h3 class="text-lg font-semibold text-gray-800 mb-2">No Rooms Available</h3>
        <p class="text-gray-600 text-sm mb-4">All rooms are currently occupied or under maintenance.</p>
        <button class="primary-bg text-white px-4 py-2 rounded-lg text-sm font-medium">
            Check Back Later
        </button>
    </div>
    <?php else: ?>
    
    <!-- Room Filters -->
    <div class="flex space-x-2 mb-4 overflow-x-auto scrollbar-hide pb-2">
        <button class="filter-btn active" data-filter="all">
            <i class="fas fa-th-large mr-2"></i>All Rooms
        </button>
        <button class="filter-btn" data-filter="standard">
            <i class="fas fa-bed mr-2"></i>Standard
        </button>
        <button class="filter-btn" data-filter="deluxe">
            <i class="fas fa-star mr-2"></i>Deluxe
        </button>
        <button class="filter-btn" data-filter="suite">
            <i class="fas fa-crown mr-2"></i>Suites
        </button>
        <button class="filter-btn" data-filter="family">
            <i class="fas fa-users mr-2"></i>Family
        </button>
    </div>
    
    <!-- Search Bar -->
    <div class="mb-4">
        <div class="relative">
            <input type="text" id="roomSearch" 
                   class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                   placeholder="Search rooms by name, amenities...">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i class="fas fa-search text-gray-400"></i>
            </div>
            <button id="clearRoomSearch" class="absolute inset-y-0 right-0 pr-3 flex items-center hidden">
                <i class="fas fa-times text-gray-400 hover:text-gray-600"></i>
            </button>
        </div>
    </div>
    
    <!-- Rooms Grid -->
    <div class="space-y-6" id="roomsContainer">
        <?php foreach($available_rooms as $index => $room): 
            $unsplash_image = getRoomUnsplashImage($room['room_type_name'] ?? 'Standard', $index);
            $amenities = array_merge(
                formatRoomAmenities($room['amenities'] ?? ''),
                formatRoomAmenities($room['room_type_amenities'] ?? '')
            );
            $amenities = array_slice(array_unique($amenities), 0, 6);
        ?>
        <div class="room-card bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-100 hover:shadow-xl transition-all duration-300"
             data-type="<?= htmlspecialchars(strtolower($room['room_type_name'] ?? '')) ?>"
             data-name="<?= htmlspecialchars(strtolower($room['room_type_name'] ?? '')) ?>"
             data-amenities="<?= htmlspecialchars(strtolower(implode(',', $amenities))) ?>"
             data-price="<?= $room['rate_per_night'] ?>">
            
            <!-- Room Image with Gradient Overlay -->
            <div class="relative h-64 overflow-hidden">
                <!-- Main Image -->
                <img src="<?= $unsplash_image ?>" 
                     alt="<?= htmlspecialchars($room['room_type_name'] ?? 'Hotel Room') ?>"
                     class="w-full h-full object-cover transition-transform duration-700 room-image"
                     loading="lazy">
                
                <!-- Gradient Overlay -->
                <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
                
                <!-- Room Number Badge -->
                <div class="absolute top-4 left-4 bg-white/95 backdrop-blur-sm text-gray-800 px-3 py-2 rounded-full text-sm font-semibold shadow-lg">
                    <i class="fas fa-door-open mr-1"></i>
                    Room <?= htmlspecialchars($room['room_number']) ?>
                </div>
                
                <!-- Status Badge -->
                <div class="absolute top-4 right-4 bg-green-500 text-white px-3 py-2 rounded-full text-sm font-semibold shadow-lg flex items-center">
                    <div class="w-2 h-2 bg-white rounded-full mr-2 animate-pulse"></div>
                    Available
                </div>
                
                <!-- Price Overlay -->
                <div class="absolute bottom-4 left-4">
                    <div class="text-2xl font-bold text-white drop-shadow-lg">
                        ₹<?= number_format($room['rate_per_night'], 0) ?>
                    </div>
                    <div class="text-white/90 text-sm drop-shadow">per night</div>
                </div>
                
                <!-- Quick View Button -->
                <button class="absolute bottom-4 right-4 bg-white/90 hover:bg-white text-gray-800 px-4 py-2 rounded-full text-sm font-medium transition-all duration-300 transform hover:scale-105 shadow-lg quick-view-btn"
                        data-room='<?= json_encode($room) ?>'>
                    <i class="fas fa-expand mr-1"></i>Quick View
                </button>
            </div>
            
            <!-- Room Details -->
            <div class="p-6">
                <div class="flex justify-between items-start mb-4">
                    <div class="flex-1">
                        <h3 class="font-bold text-gray-900 text-xl mb-2">
                            <?= htmlspecialchars($room['room_type_name'] ?? 'Standard Room') ?>
                        </h3>
                        <p class="text-gray-600 text-sm leading-relaxed mb-3">
                            <?= htmlspecialchars($room['room_type_description'] ?? 'Luxurious room with modern amenities and comfortable bedding') ?>
                        </p>
                        
                        <!-- Room Specs Grid -->
                        <div class="grid grid-cols-3 gap-4 mb-4">
                            <?php if (!empty($room['max_occupancy'])): ?>
                            <div class="flex items-center text-sm text-gray-700">
                                <i class="fas fa-user-friends mr-2 text-blue-500"></i>
                                <span><?= htmlspecialchars($room['max_occupancy']) ?> Guests</span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($room['size_sqft'])): ?>
                            <div class="flex items-center text-sm text-gray-700">
                                <i class="fas fa-expand-arrows-alt mr-2 text-green-500"></i>
                                <span><?= htmlspecialchars($room['size_sqft']) ?> sq.ft</span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($room['bed_type'])): ?>
                            <div class="flex items-center text-sm text-gray-700">
                                <i class="fas fa-bed mr-2 text-purple-500"></i>
                                <span><?= htmlspecialchars($room['bed_type']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Key Amenities -->
                <?php if (!empty($amenities)): ?>
                <div class="mb-6">
                    <h4 class="text-sm font-semibold text-gray-800 mb-3 flex items-center">
                        <i class="fas fa-star mr-2 text-yellow-500"></i>
                        Room Amenities
                    </h4>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        <?php foreach($amenities as $amenity): ?>
                        <div class="flex items-center text-xs text-gray-600 bg-gray-50 hover:bg-gray-100 px-3 py-2 rounded-lg transition-colors">
                            <i class="fas fa-<?= getRoomAmenityIcon($amenity) ?> mr-2 text-blue-500"></i>
                            <span class="truncate"><?= htmlspecialchars(ucwords($amenity)) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Action Buttons -->
                <div class="flex space-x-3">
                    <button class="flex-1 bg-gradient-to-r from-gray-100 to-gray-200 hover:from-gray-200 hover:to-gray-300 text-gray-700 py-4 rounded-xl font-semibold text-sm flex items-center justify-center transition-all duration-300 transform hover:-translate-y-0.5 shadow-md room-wishlist-btn"
                            data-room-id="<?= $room['id'] ?>"
                            data-room-number="<?= htmlspecialchars($room['room_number']) ?>">
                        <i class="far fa-heart mr-2"></i>
                        Save
                    </button>
                    <button class="flex-1 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white py-4 rounded-xl font-semibold text-sm flex items-center justify-center transition-all duration-300 transform hover:-translate-y-0.5 shadow-lg room-book-btn"
                            data-room='<?= json_encode($room) ?>'>
                        <i class="fas fa-calendar-check mr-2"></i>
                        Book Now
                    </button>
                </div>
                
                <!-- Additional Info -->
                <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-200 text-xs text-gray-500">
                    <div class="flex items-center">
                        <i class="fas fa-clock mr-2 text-green-500"></i>
                        <span>Check-in: 2:00 PM • Check-out: 12:00 PM</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-shield-alt mr-2 text-blue-500"></i>
                        <span>Free Cancellation</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Load More Button -->
    <div class="text-center mt-8">
        <button class="bg-white border-2 border-gray-300 hover:border-blue-500 text-gray-700 hover:text-blue-600 px-8 py-4 rounded-xl font-semibold text-sm transition-all duration-300 transform hover:-translate-y-1 shadow-md hover:shadow-lg"
                id="loadMoreRooms">
            <i class="fas fa-redo mr-2"></i>
            Load More Rooms
        </button>
    </div>
    <?php endif; ?>
</div>

<!-- Quick View Modal -->
<div id="quickViewModal" class="fixed inset-0 bg-black/80 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
        <div class="relative">
            <button class="absolute top-4 right-4 z-10 bg-white/90 hover:bg-white text-gray-800 w-10 h-10 rounded-full flex items-center justify-center shadow-lg transition-all duration-300 hover:scale-110"
                    onclick="closeQuickView()">
                <i class="fas fa-times"></i>
            </button>
            <div id="quickViewContent" class="overflow-y-auto max-h-[90vh]">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
// Room filtering and search functionality
document.addEventListener('DOMContentLoaded', function() {
    initializeRoomFilters();
    initializeRoomSearch();
    initializeRoomInteractions();
});

function initializeRoomFilters() {
    const filterButtons = document.querySelectorAll('.filter-btn');
    const roomCards = document.querySelectorAll('.room-card');
    
    filterButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const filter = this.dataset.filter;
            
            // Update active state
            filterButtons.forEach(b => b.classList.remove('active', 'primary-bg', 'text-white'));
            this.classList.add('active', 'primary-bg', 'text-white');
            
            // Filter rooms
            roomCards.forEach(card => {
                if (filter === 'all') {
                    card.style.display = 'block';
                } else {
                    const roomType = card.dataset.type;
                    if (roomType.includes(filter)) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                }
            });
            
            // Animate visible cards
            animateRoomCards();
        });
    });
}

function initializeRoomSearch() {
    const searchInput = document.getElementById('roomSearch');
    const clearButton = document.getElementById('clearRoomSearch');
    const roomCards = document.querySelectorAll('.room-card');
    
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        
        // Show/hide clear button
        if (searchTerm.length > 0) {
            clearButton.classList.remove('hidden');
        } else {
            clearButton.classList.add('hidden');
        }
        
        // Filter rooms
        roomCards.forEach(card => {
            if (card.style.display === 'none') return;
            
            const roomName = card.dataset.name;
            const roomAmenities = card.dataset.amenities;
            
            if (roomName.includes(searchTerm) || roomAmenities.includes(searchTerm)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
        
        animateRoomCards();
    });
    
    clearButton.addEventListener('click', function() {
        searchInput.value = '';
        this.classList.add('hidden');
        searchInput.dispatchEvent(new Event('input'));
        searchInput.focus();
    });
}

function initializeRoomInteractions() {
    // Room card animations
    const roomCards = document.querySelectorAll('.room-card');
    
    roomCards.forEach((card, index) => {
        // Entrance animation
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px) scale(0.95)';
        card.style.transition = 'all 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
        
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0) scale(1)';
        }, index * 150);
        
        // Hover effects for image
        const roomImage = card.querySelector('.room-image');
        if (roomImage) {
            card.addEventListener('mouseenter', function() {
                roomImage.style.transform = 'scale(1.05)';
                this.style.transform = 'translateY(-8px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                roomImage.style.transform = 'scale(1)';
                this.style.transform = 'translateY(0) scale(1)';
            });
        }
    });
    
    // Quick view functionality
    document.querySelectorAll('.quick-view-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const roomData = JSON.parse(this.dataset.room);
            showQuickView(roomData);
        });
    });
    
    // Book now functionality
    document.querySelectorAll('.room-book-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const roomData = JSON.parse(this.dataset.room);
            bookRoom(roomData);
        });
    });
    
    // Wishlist functionality
    document.querySelectorAll('.room-wishlist-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            toggleWishlist(this);
        });
    });
}

function animateRoomCards() {
    const visibleCards = document.querySelectorAll('.room-card[style="display: block"]');
    
    visibleCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
}

function showQuickView(roomData) {
    const modal = document.getElementById('quickViewModal');
    const content = document.getElementById('quickViewContent');
    
    const amenities = [...new Set([
        ...formatRoomAmenities(roomData.amenities || ''),
        ...formatRoomAmenities(roomData.room_type_amenities || '')
    ])];
    
    content.innerHTML = `
        <div class="p-6">
            <!-- Image Gallery -->
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="col-span-2">
                    <img src="${getRoomUnsplashImage(roomData.room_type_name, 0)}" 
                         class="w-full h-64 object-cover rounded-xl shadow-lg">
                </div>
                <div class="col-span-1">
                    <img src="${getRoomUnsplashImage(roomData.room_type_name, 1)}" 
                         class="w-full h-32 object-cover rounded-xl shadow-lg">
                </div>
                <div class="col-span-1">
                    <img src="${getRoomUnsplashImage(roomData.room_type_name, 2)}" 
                         class="w-full h-32 object-cover rounded-xl shadow-lg">
                </div>
            </div>
            
            <!-- Room Details -->
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-2">${roomData.room_type_name}</h2>
                <p class="text-gray-600 mb-4">${roomData.room_type_description || 'Luxurious room with modern amenities'}</p>
                
                <div class="grid grid-cols-3 gap-4 mb-4">
                    <div class="text-center p-4 bg-blue-50 rounded-lg">
                        <i class="fas fa-user-friends text-blue-500 text-xl mb-2"></i>
                        <div class="font-semibold">${roomData.max_occupancy || 2} Guests</div>
                    </div>
                    <div class="text-center p-4 bg-green-50 rounded-lg">
                        <i class="fas fa-expand-arrows-alt text-green-500 text-xl mb-2"></i>
                        <div class="font-semibold">${roomData.size_sqft || '200'} sq.ft</div>
                    </div>
                    <div class="text-center p-4 bg-purple-50 rounded-lg">
                        <i class="fas fa-bed text-purple-500 text-xl mb-2"></i>
                        <div class="font-semibold">${roomData.bed_type || 'Double Bed'}</div>
                    </div>
                </div>
            </div>
            
            <!-- Amenities -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center">
                    <i class="fas fa-star text-yellow-500 mr-2"></i>
                    Room Amenities
                </h3>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    ${amenities.map(amenity => `
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <i class="fas fa-${getRoomAmenityIcon(amenity)} text-blue-500 mr-3"></i>
                            <span class="text-sm font-medium">${amenity}</span>
                        </div>
                    `).join('')}
                </div>
            </div>
            
            <!-- Pricing & Booking -->
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-6">
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <div class="text-3xl font-bold text-gray-900">₹${roomData.rate_per_night?.toLocaleString() || '0'}</div>
                        <div class="text-gray-600">per night + taxes</div>
                    </div>
                    <button class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-4 rounded-xl font-semibold text-lg transition-all duration-300 transform hover:scale-105 shadow-lg"
                            onclick="bookRoom(${JSON.stringify(roomData).replace(/"/g, '&quot;')}); closeQuickView();">
                        <i class="fas fa-calendar-check mr-2"></i>
                        Book Now
                    </button>
                </div>
                <div class="flex justify-between text-sm text-gray-600">
                    <div class="flex items-center">
                        <i class="fas fa-clock text-green-500 mr-2"></i>
                        Check-in: 2:00 PM
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-shield-alt text-blue-500 mr-2"></i>
                        Free Cancellation
                    </div>
                </div>
            </div>
        </div>
    `;
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
}

function closeQuickView() {
    const modal = document.getElementById('quickViewModal');
    modal.classList.remove('flex');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function bookRoom(roomData) {
    // Show booking confirmation
    showToast(`Booking ${roomData.room_type_name} - Room ${roomData.room_number}`, 'success');
    
    // Here you would typically redirect to booking page or show booking form
    console.log('Booking room:', roomData);
    
    // Example: Redirect to booking page
    // window.location.href = `booking.php?room_id=${roomData.id}&room_number=${roomData.room_number}`;
}

function toggleWishlist(button) {
    const icon = button.querySelector('i');
    const isWishlisted = icon.classList.contains('fas');
    
    if (isWishlisted) {
        icon.classList.remove('fas', 'text-red-500');
        icon.classList.add('far');
        showToast('Removed from wishlist', 'info');
    } else {
        icon.classList.remove('far');
        icon.classList.add('fas', 'text-red-500');
        showToast('Added to wishlist', 'success');
    }
    
    // Add animation
    button.style.transform = 'scale(0.95)';
    setTimeout(() => {
        button.style.transform = 'scale(1)';
    }, 150);
}

// Utility functions
function formatRoomAmenities(amenitiesJson) {
    if (!amenitiesJson) return [];
    try {
        const amenities = JSON.parse(amenitiesJson);
        return Array.isArray(amenities) ? amenities : [];
    } catch {
        return amenitiesJson.split(',').map(a => a.trim()).filter(a => a);
    }
}

function showToast(message, type = 'success') {
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 z-50 px-6 py-4 rounded-xl shadow-lg text-white font-semibold transform translate-x-full transition-transform duration-300 ${
        type === 'success' ? 'bg-green-500' : 
        type === 'error' ? 'bg-red-500' : 'bg-blue-500'
    }`;
    toast.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation' : 'info'}-circle mr-3"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Animate in
    setTimeout(() => {
        toast.style.transform = 'translateX(0)';
    }, 100);
    
    // Auto remove
    setTimeout(() => {
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }, 3000);
}

// Close modal on backdrop click
document.getElementById('quickViewModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeQuickView();
    }
});

// Load more rooms functionality
document.getElementById('loadMoreRooms')?.addEventListener('click', function() {
    // Simulate loading more rooms
    this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Loading...';
    this.disabled = true;
    
    setTimeout(() => {
        this.innerHTML = '<i class="fas fa-redo mr-2"></i>Load More Rooms';
        this.disabled = false;
        showToast('No more rooms available', 'info');
    }, 1500);
});
</script>

<style>
.filter-btn {
    @apply bg-white border border-gray-300 px-4 py-3 rounded-xl text-sm font-medium whitespace-nowrap flex items-center transition-all duration-300 hover:shadow-md;
}

.filter-btn.active {
    @apply primary-bg text-white border-transparent shadow-lg transform -translate-y-0.5;
}

.room-card {
    transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

.room-image {
    transition: transform 0.7s ease-in-out;
}

.quick-view-btn, .room-book-btn, .room-wishlist-btn {
    transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.scrollbar-hide::-webkit-scrollbar {
    display: none;
}

/* Custom scrollbar for modal */
#quickViewContent::-webkit-scrollbar {
    width: 6px;
}

#quickViewContent::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

#quickViewContent::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

#quickViewContent::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}
</style>
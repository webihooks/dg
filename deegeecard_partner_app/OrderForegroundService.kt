package com.example.deegeecard_partner_app

import android.app.*
import android.content.Context
import android.content.Intent
import android.os.Build
import android.os.IBinder
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat

class OrderForegroundService : Service() {
    
    companion object {
        const val CHANNEL_ID = "OrderForegroundServiceChannel"
        const val NOTIFICATION_CHANNEL_ID = "NewOrdersChannel"
        const val NOTIFICATION_ID = 1001
        const val ORDER_NOTIFICATION_ID = 1002
        const val ACTION_START_SERVICE = "START_FOREGROUND_SERVICE"
        const val ACTION_STOP_SERVICE = "STOP_FOREGROUND_SERVICE"
    }
    
    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        when (intent?.action) {
            ACTION_START_SERVICE -> {
                startForegroundService()
                startOrderChecking()
            }
            ACTION_STOP_SERVICE -> {
                stopForegroundService()
            }
        }
        return START_STICKY
    }
    
    private fun startForegroundService() {
        createNotificationChannel()
        
        val notificationIntent = Intent(this, MainActivity::class.java)
        val pendingIntent = PendingIntent.getActivity(
            this,
            0, notificationIntent, PendingIntent.FLAG_IMMUTABLE
        )
        
        val notification = NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle("DeeGeeCard Partner")
            .setContentText("Monitoring for new orders...")
            .setSmallIcon(R.mipmap.ic_launcher)
            .setContentIntent(pendingIntent)
            .setPriority(NotificationCompat.PRIORITY_LOW)
            .setOngoing(true)
            .setOnlyAlertOnce(true)
            .build()
        
        startForeground(NOTIFICATION_ID, notification)
    }
    
    private fun createNotificationChannel() {
        // Channel for foreground service
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val serviceChannel = NotificationChannel(
                CHANNEL_ID,
                "Order Monitoring Service",
                NotificationManager.IMPORTANCE_LOW
            ).apply {
                description = "Monitors for new restaurant orders"
                setShowBadge(false)
                lockscreenVisibility = Notification.VISIBILITY_PUBLIC
            }
            
            // Channel for order notifications
            val orderChannel = NotificationChannel(
                NOTIFICATION_CHANNEL_ID,
                "New Orders",
                NotificationManager.IMPORTANCE_HIGH
            ).apply {
                description = "Notifications for new orders"
                setShowBadge(true)
                lockscreenVisibility = Notification.VISIBILITY_PUBLIC
            }
            
            val manager = getSystemService(NotificationManager::class.java)
            manager.createNotificationChannel(serviceChannel)
            manager.createNotificationChannel(orderChannel)
        }
    }
    
    private fun startOrderChecking() {
        // Background order checking logic will be implemented here
        // For now, we just keep the service running
    }
    
    fun showOrderNotification(orderId: String, customerName: String, totalAmount: Double) {
        val notificationIntent = Intent(this, MainActivity::class.java)
        val pendingIntent = PendingIntent.getActivity(
            this,
            0, notificationIntent, PendingIntent.FLAG_IMMUTABLE
        )
        
        val notification = NotificationCompat.Builder(this, NOTIFICATION_CHANNEL_ID)
            .setContentTitle("New Order Received! 🎉")
            .setContentText("Order #$orderId from $customerName - ₹$totalAmount")
            .setSmallIcon(R.mipmap.ic_launcher)
            .setContentIntent(pendingIntent)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setAutoCancel(true)
            .build()
        
        with(NotificationManagerCompat.from(this)) {
            notify(ORDER_NOTIFICATION_ID, notification)
        }
    }
    
    private fun stopForegroundService() {
        stopForeground(true)
        stopSelf()
    }
    
    override fun onBind(intent: Intent?): IBinder? = null
}
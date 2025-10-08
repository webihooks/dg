package com.example.deegeecard_partner_app

import android.app.*
import android.content.Context
import android.content.Intent
import android.os.Build
import android.os.IBinder
import androidx.core.app.NotificationCompat

class OrderForegroundService : Service() {
    
    companion object {
        const val CHANNEL_ID = "OrderForegroundServiceChannel"
        const val NOTIFICATION_ID = 1001
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
            
            val manager = getSystemService(NotificationManager::class.java)
            manager.createNotificationChannel(serviceChannel)
        }
    }
    
    private fun startOrderChecking() {
        // This is where we'll implement background order checking
        // For Phase 1, we just keep the service running
    }
    
    private fun stopForegroundService() {
        stopForeground(true)
        stopSelf()
    }
    
    override fun onBind(intent: Intent?): IBinder? = null
}
package com.example.deegeecard_partner_app

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.os.Build

class BootCompleteReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action == Intent.ACTION_BOOT_COMPLETED || 
            intent.action == "android.intent.action.QUICKBOOT_POWERON") {
            
            // We'll implement auto-restart logic in Phase 2
            // For now, just log that boot was detected
            println("Device booted - Order monitoring service can be restarted")
        }
    }
}
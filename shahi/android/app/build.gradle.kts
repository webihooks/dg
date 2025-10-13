plugins {
    id("com.android.application")
    id("kotlin-android")
    id("dev.flutter.flutter-gradle-plugin")
}

android {
    namespace = "com.example.shahi"
    compileSdk = 35 // Consider latest stable (35 is preview as of my knowledge)
    ndkVersion = "27.0.12077973"

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_11
        targetCompatibility = JavaVersion.VERSION_11
        isCoreLibraryDesugaringEnabled = true // For Java 8+ APIs on older devices
    }

    kotlinOptions {
        jvmTarget = "11"
    }

    defaultConfig {
        applicationId = "com.yourcompany.biryanibybulk"
        minSdk = flutter.minSdkVersion
        targetSdk = 34
        versionCode = flutter.versionCode?.toInt() ?: 1
        versionName = flutter.versionName ?: "1.0"
        multiDexEnabled = true // For larger apps
        vectorDrawables.useSupportLibrary = true
    }

    buildTypes {
        release {
            // Consider proper signing config for release
            signingConfig = signingConfigs.getByName("debug") // Remove for production!
            isMinifyEnabled = true // Should be true for release
            isShrinkResources = true // Should be true for release
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro"
            )
            ndk {
                abiFilters.addAll(listOf("armeabi-v7a", "arm64-v8a", "x86_64"))
            }
        }
        debug {
            applicationIdSuffix = ".debug"
            versionNameSuffix = "-DEBUG"
            isMinifyEnabled = false
            isShrinkResources = false
        }
    }

    packagingOptions {
        resources.excludes.addAll(
            listOf(
                "META-INF/*",
                "META-INF/NOTICE.md",
                "META-INF/LICENSE.md",
                "/META-INF/{AL2.0,LGPL2.1}"
            )
        )
        jniLibs.useLegacyPackaging = true // For newer Gradle versions
    }

    // Enable view binding if needed
    buildFeatures {
        viewBinding = true
        buildConfig = true
    }
}

flutter {
    source = "../.."
}

dependencies {
    implementation("org.jetbrains.kotlin:kotlin-stdlib-jdk8:1.9.20")
    implementation("androidx.core:core-ktx:1.12.0")
    implementation("androidx.webkit:webkit:1.8.0")
    implementation("androidx.multidex:multidex:2.0.1") // For multi-dex support
    coreLibraryDesugaring("com.android.tools:desugar_jdk_libs:2.0.4") // For Java 8+ APIs
    
    // Consider adding these for better WebView functionality:
    implementation("androidx.browser:browser:1.6.0") // For Chrome Custom Tabs
    implementation("com.google.android.material:material:1.10.0") // Material components
}

package com.flashpay.gateway

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Shapes
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp
import androidx.compose.foundation.shape.RoundedCornerShape

object Brand {
    // Mêmes couleurs que le site FlashDépôt.
    val Orange = Color(0xFFFF6A00)
    val Orange2 = Color(0xFFFF8A1F)
    val OrangeDeep = Color(0xFFF05200)
    val OrangeSoft = Color(0xFFFFF0E4)
    val Navy = Color(0xFF121418)
    val Blue = Orange          // ancien nom : accent principal
    val BlueLight = Orange2
    val BlueSoft = OrangeSoft
    val Green = Color(0xFF12804A)
    val GreenSoft = Color(0xFFE4F5EC)
    val Amber = Color(0xFFA15C00)
    val AmberSoft = Color(0xFFFFF3D9)
    val Red = Color(0xFFD32F2F)
    val RedSoft = Color(0xFFFDECEC)
}

private val LightColors = lightColorScheme(
    primary = Brand.Orange,
    onPrimary = Color.White,
    primaryContainer = Brand.OrangeSoft,
    onPrimaryContainer = Color(0xFF7A2E00),
    secondary = Brand.Navy,
    background = Color(0xFFF5F6F8),
    onBackground = Color(0xFF121418),
    surface = Color.White,
    onSurface = Color(0xFF121418),
    surfaceVariant = Color(0xFFF0F1F4),
    onSurfaceVariant = Color(0xFF6B7280),
    outline = Color(0xFFE7E8EC),
    outlineVariant = Color(0xFFE7E8EC),
    error = Brand.Red,
)

private val DarkColors = darkColorScheme(
    primary = Brand.Orange2,
    onPrimary = Color.White,
    primaryContainer = Color(0xFF3A2310),
    onPrimaryContainer = Color(0xFFFFDCC2),
    secondary = Color(0xFFCBD5E1),
    background = Color(0xFF0F1115),
    onBackground = Color(0xFFEEF0F3),
    surface = Color(0xFF181B21),
    onSurface = Color(0xFFEEF0F3),
    surfaceVariant = Color(0xFF20242B),
    onSurfaceVariant = Color(0xFF9AA1AD),
    outline = Color(0xFF2A2F37),
    outlineVariant = Color(0xFF2A2F37),
    error = Color(0xFFFF6B6B),
)

@Composable
fun GatewayTheme(content: @Composable () -> Unit) {
    MaterialTheme(
        colorScheme = if (isSystemInDarkTheme()) DarkColors else LightColors,
        shapes = Shapes(
            small = RoundedCornerShape(12.dp),
            medium = RoundedCornerShape(16.dp),
            large = RoundedCornerShape(24.dp),
        ),
        content = content,
    )
}

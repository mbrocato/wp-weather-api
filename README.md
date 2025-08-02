# Custom WordPress Weather API Integration

WordPress plugin to fetch weather data from a local Raspberry Pi weather station (based on [elewin/pi-weather-station](https://github.com/elewin/pi-weather-station)).

## Setup

1. **Install on WordPress:**
   - Upload the plugin folder to `/wp-content/plugins/`.
   - Activate the plugin in the WordPress Plugins menu.
   - Go to Settings > Weather API in the WP admin dashboard to enter your Pi API key (generated from the Node.js server).

2. **Usage:**
   - Shortcode: `[weather_display]` – Add this to any page/post to show current weather.
   - Widget: Add the 'Weather Display' widget to a sidebar for dynamic display.
   - REST API: Access data programmatically via `/wp-json/weather/v1/current` (GET request).

## Features
- Fetches real-time weather data (temperature, humidity, wind speed) from a local Raspberry Pi via REST API.
- Handles authentication with API keys and edge cases like network errors or invalid responses.
- Displays data in a simple widget/shortcode with customizable CSS (via .weather-widget class).
- Includes admin settings for API key management and a custom database table for optional caching.

## Node.js Configuration for Raspberry Pi (Required for Functionality)
This plugin relies on a Node.js server running on your Raspberry Pi to expose weather data endpoints. It's designed to work with the [elewin/pi-weather-station](https://github.com/elewin/pi-weather-station) repository, which collects sensor data (e.g., from DHT22 for temperature/humidity). Follow these steps to set up the Node.js side:

### Prerequisites
- Raspberry Pi with Node.js installed (v14+ recommended).
- Sensors connected (e.g., DHT22 for temp/humidity, anemometer for wind).
- Clone the elewin repo: `git clone https://github.com/elewin/pi-weather-station.git && cd pi-weather-station`.

### Configuration Steps
1. **Install Dependencies:**
   - Run `npm install` to install required packages (e.g., express for the server, node-dht-sensor for readings).

2. **Configure the Server:**
   - Edit `server.js` (or the main file in elewin's repo) to expose a `/weather/current` endpoint. Add API key authentication for security.
   - Example updated `server.js` snippet (add if not present):

     ```javascript
     const express = require('express');
     const app = express();
     const port = 3000;  // Or your preferred port

     // Mock sensor data (replace with actual readings from elewin's sensor code)
     function getWeatherData() {
         return {
             temperature: 25.5,  // From DHT22
             humidity: 60,       // From DHT22
             wind_speed: 10      // From anemometer
         };
     }

     // Middleware for API key auth
     function authenticate(req, res, next) {
         const apiKey = req.header('Authorization');
         if (!apiKey || apiKey !== 'Bearer your_secret_api_key') {  // Replace with your key
             return res.status(403).json({ error: 'Invalid API key' });
         }
         next();
     }

     app.get('/weather/current', authenticate, (req, res) => {
         const data = getWeatherData();
         res.json(data);
     });

     app.listen(port, () => {
         console.log(`Server running at http://raspberrypi.local:${port}`);
     });
     ```

   - Replace `your_secret_api_key` with a secure key (generate via `openssl rand -hex 32`).
   - Integrate actual sensor readings from elewin's code (e.g., replace `getWeatherData()` with calls to DHT sensor functions).

3. **Run the Server:**
   - Start with `node server.js` or use PM2 for persistence: `pm2 start server.js --name weather-server`.
   - Ensure the Pi is on your local network and accessible (e.g., via `http://raspberrypi.local:3000`). Set a static IP if needed in `/etc/dhcpcd.conf`.
   - Test endpoint: `curl -H "Authorization: Bearer your_secret_api_key" http://raspberrypi.local:3000/weather/current` – Should return JSON like `{"temperature":25.5,"humidity":60,"wind_speed":10}`.

4. **Security and Edge Cases:**
   - Use HTTPS on Pi if exposed (self-signed cert via OpenSSL).
   - Handle sensor failures in `getWeatherData()`: Add try-catch to return defaults or errors.
   - Firewall: Open port 3000 on Pi with `sudo ufw allow 3000`.

## License
MIT
```

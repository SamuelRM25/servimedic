<div class="real-time-clock" id="realTimeClock">
    <div class="clock-time" id="clockTime">00:00:00</div>
    <div class="clock-date" id="clockDate">Cargando...</div>
</div>

<style>
    .real-time-clock {
        background: var(--color-white);
        padding: 0.5rem 1rem;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--color-border);
        min-width: 140px;
    }

    .clock-time {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--color-orange);
        font-family: 'Courier New', monospace;
        line-height: 1.2;
    }

    .clock-date {
        font-size: 0.75rem;
        color: var(--color-text-light);
        font-weight: 500;
        text-transform: capitalize;
    }
</style>

<script>
    function updateClock() {
        const now = new Date();
        
        // Time
        const timeString = now.toLocaleTimeString('es-GT', { 
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit',
            hour12: true 
        });
        document.getElementById('clockTime').textContent = timeString;
        
        // Date
        const dateString = now.toLocaleDateString('es-GT', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
        document.getElementById('clockDate').textContent = dateString;
    }

    // Update immediately and then every second
    updateClock();
    setInterval(updateClock, 1000);
</script>

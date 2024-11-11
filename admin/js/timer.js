jQuery(document).ready(function($) {
    let timers = {};

    function formatTime(seconds) {
        const hrs = Math.floor(seconds / 3600);
        const mins = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        return [hrs, mins, secs].map(v => String(v).padStart(2, '0')).join(':');
    }

    function startTimer(clientId) {
        // Initialize timer if not already set
        if (!timers[clientId]) {
            timers[clientId] = { startTime: null, interval: null, pausedTime: 0 };
        }

        // If there's no start time, set it to now, and store the running state
        if (!timers[clientId].startTime) {
            timers[clientId].startTime = Date.now() - (timers[clientId].pausedTime * 1000);
            timers[clientId].pausedTime = 0;
            localStorage.setItem(`timer_start_${clientId}`, timers[clientId].startTime);
            localStorage.setItem(`timer_state_${clientId}`, 'running');
        }

        // Start the interval if not already running
        if (!timers[clientId].interval) {
            timers[clientId].interval = setInterval(() => {
                const currentTime = Date.now();
                const elapsedSeconds = Math.floor((currentTime - timers[clientId].startTime) / 1000);
                $(`#timer-display-${clientId}`).text(formatTime(elapsedSeconds));
            }, 1000);
        }
    }

    function pauseTimer(clientId) {
        if (timers[clientId] && timers[clientId].interval) {
            clearInterval(timers[clientId].interval);
            timers[clientId].interval = null;

            // Calculate elapsed time until paused and save it
            const currentTime = Date.now();
            timers[clientId].pausedTime = Math.floor((currentTime - timers[clientId].startTime) / 1000);
            localStorage.setItem(`timer_elapsed_${clientId}`, timers[clientId].pausedTime);
            localStorage.setItem(`timer_state_${clientId}`, 'paused');
            localStorage.removeItem(`timer_start_${clientId}`);
        }
    }

    $('.start-timer').on('click', function() {
        const clientId = $(this).data('client-id');
        $(this).prop('disabled', true);
        $(`.pause-timer[data-client-id=${clientId}]`).prop('disabled', false);
        $(`.log-time[data-client-id=${clientId}]`).prop('disabled', false);

        startTimer(clientId);
    });

    $('.pause-timer').click(function() {
        const clientId = $(this).data('client-id');
        pauseTimer(clientId);
        $(this).prop('disabled', true);
        $(`.start-timer[data-client-id=${clientId}]`).prop('disabled', false);
    });

    $('.log-time').click(function() {
        const clientId = $(this).data('client-id');
        const elapsed = timers[clientId].pausedTime || Math.floor((Date.now() - timers[clientId].startTime) / 1000);

        $.post(wpTimeLogging.ajax_url, {
            action: 'log_time',
            client_id: clientId,
            time_logged: elapsed
        }, function(response) {
            if (response.success) {
                // Update the remaining time in the UI
                $(`.remaining-time[data-client-id=${clientId}]`).text(response.data.remaining_time);

                // Update the current plan time in the UI
                // $(`.current-plan-time[data-client-id=${clientId}]`).text(response.data.current_plan_time);

                // Update the total logged time in the UI
                $(`.overall-time[data-client-id=${clientId}]`).text(response.data.overall_time);

                // Stop and reset timer on successful log
                clearInterval(timers[clientId].interval);
                timers[clientId] = { startTime: null, interval: null, pausedTime: 0 };
                $(`#timer-display-${clientId}`).text("00:00:00");
                localStorage.removeItem(`timer_start_${clientId}`);
                localStorage.removeItem(`timer_elapsed_${clientId}`);
                localStorage.removeItem(`timer_state_${clientId}`);

                $(`.pause-timer[data-client-id=${clientId}], .log-time[data-client-id=${clientId}]`).prop('disabled', true);
                $(`.start-timer[data-client-id=${clientId}]`).prop('disabled', false);

                // Show success message
                const successMessage = $(`#success-message-${clientId}`);
                successMessage.show();
                setTimeout(() => successMessage.fadeOut(), 3000); // Hide after 3 seconds
            } else {
                alert("Error logging time");
            }
        });
    });

    $('.reset-timer').click(function() {
        const clientId = $(this).data('client-id');
        clearInterval(timers[clientId].interval);
        timers[clientId] = { startTime: null, interval: null, pausedTime: 0 };
        $(`#timer-display-${clientId}`).text("00:00:00");
        localStorage.removeItem(`timer_start_${clientId}`);
        localStorage.removeItem(`timer_elapsed_${clientId}`);
        localStorage.removeItem(`timer_state_${clientId}`);

        $(`.start-timer[data-client-id=${clientId}]`).prop('disabled', false);
        $(`.pause-timer[data-client-id=${clientId}], .log-time[data-client-id=${clientId}]`).prop('disabled', true);
    });

    // Restore timers on page load
    $('.timer-display').each(function() {
        const clientId = $(this).data('client-id');
        const savedState = localStorage.getItem(`timer_state_${clientId}`);
        const savedStartTime = localStorage.getItem(`timer_start_${clientId}`);
        const savedPausedTime = localStorage.getItem(`timer_elapsed_${clientId}`);

        if (savedState === 'running' && savedStartTime) {
            // Timer was running, so continue it
            timers[clientId] = { startTime: parseInt(savedStartTime, 10), interval: null, pausedTime: 0 };
            startTimer(clientId);
            $(`.start-timer[data-client-id=${clientId}]`).prop('disabled', true);
            $(`.pause-timer[data-client-id=${clientId}], .log-time[data-client-id=${clientId}]`).prop('disabled', false);
        } else if (savedState === 'paused' && savedPausedTime) {
            // Timer was paused, so display the paused time without running
            timers[clientId] = { startTime: null, interval: null, pausedTime: parseInt(savedPausedTime, 10) };
            $(`#timer-display-${clientId}`).text(formatTime(timers[clientId].pausedTime));
            $(`.start-timer[data-client-id=${clientId}]`).prop('disabled', false);
            $(`.pause-timer[data-client-id=${clientId}], .log-time[data-client-id=${clientId}]`).prop('disabled', true);
        }
    });


    // Add new plan = additional hours
    $('.add-plan').on('click', function(){
        const clientId = $(this).data('client-id');
        console.log('clicked');
        console.log(clientId);

        $.post(wpTimeLogging.ajax_url, {
            action: 'add_new_plan',
            client_id: clientId
        }, function(response) {
                $(`.remaining-time[data-client-id=${clientId}]`).text(response.data.remaining_time);
                const successMessage = $(`#plan-message-${clientId}`);
                successMessage.show();
                setTimeout(() => successMessage.fadeOut(), 3000); // Hide after 3 seconds
        });
    });
});

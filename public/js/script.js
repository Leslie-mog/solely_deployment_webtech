document.addEventListener('DOMContentLoaded', () => {
   //Select the video element and the placeholder image i.e. the film poster
    const heroVideo = document.getElementById('hero-video');
    const heroCover = document.getElementById('hero-cover');

    // Only run this logic if the hero video actually exists on the current page
    if (heroVideo) {
        //make the placeholder transparent and the video visible
        if (heroCover) {
            heroCover.style.opacity = '0';
        }
        heroVideo.style.opacity = '1';
        
        // playback attempt to start the video immediately
        heroVideo.play().catch(e => {
            console.log("Auto-play prevented:", e);
            // If autoplay fails, try again on user interaction
            document.addEventListener('click', () => {
                heroVideo.play().catch(err => console.log("Play failed:", err));
            }, { once: true });
        });
        
        // Ensure video starts again from the beginning when it ends
        heroVideo.addEventListener('ended', () => {
            heroVideo.currentTime = 0;
            heroVideo.play();
        });
    }

    // Converts vertical mouse wheel movement into horizontal scrolling for rows.
    const scrollContainers = document.querySelectorAll('.scroll-container');
    scrollContainers.forEach(container => {
        container.addEventListener('wheel', (evt) => {
            evt.preventDefault();
            container.scrollLeft += evt.deltaY;
        });
    });

    // hover effects
    const cards = document.querySelectorAll('.card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', () => {
            console.log('Hovering card');
        });
    });
});

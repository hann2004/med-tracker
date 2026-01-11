// Particle Configuration
particlesJS('particles-js', {
    particles: {
        number: { value: 100, density: { enable: true, value_area: 800 } },
        color: { value: ["#00d4ff", "#9d4edd", "#00ffd5", "#00ff88"] },
        shape: { type: "circle" },
        opacity: { value: 0.5, random: true },
        size: { value: 4, random: true },
        line_linked: {
            enable: true,
            distance: 150,
            color: "#00d4ff",
            opacity: 0.2,
            width: 1
        },
        move: {
            enable: true,
            speed: 3,
            direction: "none",
            random: true,
            straight: false,
            out_mode: "out",
            bounce: false
        }
    },
    interactivity: {
        detect_on: "canvas",
        events: {
            onhover: { enable: true, mode: "repulse" },
            onclick: { enable: true, mode: "push" },
            resize: true
        }
    },
    retina_detect: true
});
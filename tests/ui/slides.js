document.addEventListener("DOMContentLoaded", () => {
  const slides = Array.from(document.querySelectorAll(".slide"));
  const prevBtn = document.getElementById("prev-slide");
  const nextBtn = document.getElementById("next-slide");
  const indicator = document.getElementById("slide-index-label");
  const progressBar = document.getElementById("progress-bar");
  const timerDisplay = document.getElementById("presentation-timer");
  const toggleAutoplayBtn = document.getElementById("toggle-autoplay");
  const playIcon = document.getElementById("play-icon");
  const pauseIcon = document.getElementById("pause-icon");
  const toggleNotesBtn = document.getElementById("toggle-notes");
  const closeNotesBtn = document.getElementById("close-notes");
  const notesPanel = document.getElementById("speaker-notes-panel");
  const notesContent = document.getElementById("notes-content");
  const fullscreenBtn = document.getElementById("fullscreen-slides");

  let currentIdx = 0;
  let autoplayInterval = null;
  const autoplayDuration = 8000; // 8 seconds per slide
  let totalSeconds = 0;
  let timerInterval = null;

  // Timer Implementation
  function startTimer() {
    timerInterval = setInterval(() => {
      totalSeconds++;
      const mins = String(Math.floor(totalSeconds / 60)).padStart(2, "0");
      const secs = String(totalSeconds % 60).padStart(2, "0");
      timerDisplay.textContent = `${mins}:${secs}`;
    }, 1000);
  }

  function resetTimer() {
    totalSeconds = 0;
    timerDisplay.textContent = "00:00";
  }

  timerDisplay.addEventListener("click", () => {
    resetTimer();
  });

  // Slide Navigation
  function updateSlides() {
    slides.forEach((slide, idx) => {
      slide.classList.remove("active", "exit-left");
      if (idx === currentIdx) {
        slide.classList.add("active");
        // Update notes content
        const notes = slide.querySelector(".slide-notes");
        notesContent.innerHTML = notes ? notes.innerHTML.trim() : "Không có ghi chú cho slide này.";
      } else if (idx < currentIdx) {
        slide.classList.add("exit-left");
      }
    });

    // Update controls & indicators
    indicator.textContent = `${currentIdx + 1} / ${slides.length}`;
    progressBar.style.width = `${((currentIdx + 1) / slides.length) * 100}%`;
    prevBtn.disabled = currentIdx === 0;
    nextBtn.disabled = currentIdx === slides.length - 1;
  }

  function nextSlide() {
    if (currentIdx < slides.length - 1) {
      currentIdx++;
      updateSlides();
    } else if (autoplayInterval) {
      // If playing, wrap to first
      currentIdx = 0;
      updateSlides();
    }
  }

  function prevSlide() {
    if (currentIdx > 0) {
      currentIdx--;
      updateSlides();
    }
  }

  // Event Listeners for Nav
  prevBtn.addEventListener("click", prevSlide);
  nextBtn.addEventListener("click", nextSlide);

  // Keyboard navigation
  document.addEventListener("keydown", (e) => {
    if (e.key === "ArrowRight" || e.key === "ArrowDown" || e.key === "Space") {
      e.preventDefault();
      nextSlide();
    } else if (e.key === "ArrowLeft" || e.key === "ArrowUp") {
      e.preventDefault();
      prevSlide();
    } else if (e.key === "PageDown") {
      e.preventDefault();
      nextSlide();
    } else if (e.key === "PageUp") {
      e.preventDefault();
      prevSlide();
    } else if (e.key.toLowerCase() === "n") {
      toggleNotes();
    } else if (e.key.toLowerCase() === "f") {
      toggleFullscreen();
    }
  });

  // Autoplay functionality
  function toggleAutoplay() {
    if (autoplayInterval) {
      clearInterval(autoplayInterval);
      autoplayInterval = null;
      playIcon.classList.remove("hidden");
      pauseIcon.classList.add("hidden");
      toggleAutoplayBtn.title = "Tự động phát";
    } else {
      playIcon.classList.add("hidden");
      pauseIcon.classList.remove("hidden");
      toggleAutoplayBtn.title = "Dừng tự động phát";
      autoplayInterval = setInterval(nextSlide, autoplayDuration);
    }
  }

  toggleAutoplayBtn.addEventListener("click", toggleAutoplay);

  // Speaker Notes Toggle
  function toggleNotes() {
    notesPanel.classList.toggle("active");
  }

  toggleNotesBtn.addEventListener("click", toggleNotes);
  closeNotesBtn.addEventListener("click", () => {
    notesPanel.classList.remove("active");
  });

  // Fullscreen support
  function toggleFullscreen() {
    const doc = document.documentElement;
    if (!document.fullscreenElement) {
      doc.requestFullscreen().catch((err) => {
        console.error(`Error enabling fullscreen: ${err.message}`);
      });
    } else {
      document.exitFullscreen();
    }
  }

  fullscreenBtn.addEventListener("click", toggleFullscreen);

  // Touch gestures for mobile swipes
  let startX = 0;
  document.addEventListener("touchstart", (e) => {
    startX = e.touches[0].clientX;
  });

  document.addEventListener("touchend", (e) => {
    const diffX = e.changedTouches[0].clientX - startX;
    if (Math.abs(diffX) > 60) {
      if (diffX > 0) {
        prevSlide();
      } else {
        nextSlide();
      }
    }
  });

  // Initialize
  updateSlides();
  startTimer();
});

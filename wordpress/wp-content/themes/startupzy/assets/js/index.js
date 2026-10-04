
"use strict";
const startupzyAnimateObserves = document.getElementsByClassName('startupzy-animate');

let startupzyanimateobserve = new IntersectionObserver(entries => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            let item = entry.target;
            if(document.querySelector('.startupzy-page-preloader') !== null && window.scrollY < 150){
                setTimeout(() => {item.classList.add('startupzy-animate-init');}, 300);
            }else{
                item.classList.add('startupzy-animate-init');
            }
            //startupzyanimateobserve.disconnect();
        }
    });
}, {threshold: 0.5});

for (let itemobserve of startupzyAnimateObserves) {
    startupzyanimateobserve.observe(itemobserve);
}
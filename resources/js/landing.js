/**
 * The example on the public page: a run moves along the line and holds at the signal until the visitor answers.
 * With reduced motion it goes straight to the signal. Without script the line and the words still read.
 */
const demo = document.querySelector('[data-demo]');

if (demo) {
    const stops = [...demo.querySelectorAll('[data-stop]')];
    const hold = stops.findIndex((stop) => stop.hasAttribute('data-hold'));
    const state = demo.querySelector('[data-state]');
    const card = demo.querySelector('[data-card]');
    const calm = matchMedia('(prefers-reduced-motion: reduce)').matches;
    const pause = (ms) => new Promise((resolve) => setTimeout(resolve, calm ? 0 : ms));

    const say = (words, tone = '') => {
        state.textContent = words;
        state.dataset.tone = tone;
    };

    const reach = (index) => stops.forEach((stop, i) => {
        stop.classList.toggle('done', i < index);
        stop.classList.toggle('now', i === index);
    });

    const run = async (from, to) => {
        for (let index = from; index <= to; index++) {
            reach(index);
            await pause(800);
        }
    };

    const start = async () => {
        stops.forEach((stop) => stop.classList.remove('done', 'now', 'refused'));
        card.hidden = true;
        say('Running');
        await run(0, hold - 1);
        reach(hold);
        say('Waiting for a person', 'hold');
        card.hidden = false;
    };

    demo.addEventListener('click', async (event) => {
        const answer = event.target.closest('[data-answer]')?.dataset.answer;

        if (!answer) return;

        card.hidden = true;

        if (answer === 'yes') {
            say('Running');
            await run(hold, stops.length - 1);
            stops.at(-1).classList.replace('now', 'done');
            say('Finished', 'clear');
        } else {
            stops[hold].classList.remove('now');
            stops[hold].classList.add('refused');
            say('Stopped. Nothing was sent', 'stop');
        }

        await pause(3200);

        if (!calm) start();
    });

    const seen = new IntersectionObserver(([entry]) => {
        if (entry.isIntersecting) {
            seen.disconnect();
            start();
        }
    }, { threshold: 0.4 });

    seen.observe(demo);
}

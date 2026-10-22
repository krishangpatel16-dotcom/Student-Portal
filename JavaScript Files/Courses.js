document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('courseModal');
    const modalPanel = modal.querySelector('.course-detail-modal');
    const code = document.getElementById('courseModalCode');
    const title = document.getElementById('courseModalTitle');
    const description = document.getElementById('courseModalDescription');
    const topics = document.getElementById('courseModalTopics');
    const closeButtons = modal.querySelectorAll('.course-modal-close, .course-modal-done');

    const closeCourse = () => {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
    };

    const courseDetails = {
        discrete: {
            code: 'MSUD203',
            title: 'Discrete Mathematics',
            description: 'Study the mathematical structures used to reason about algorithms, software, and computer systems. The course emphasizes precise definitions, logical proofs, and problem-solving.',
            topics: ['Propositional and predicate logic', 'Sets, relations, and functions', 'Counting principles and recurrence relations', 'Graph theory and trees', 'Mathematical induction']
        },
        web: {
            code: 'ITUE203',
            title: 'Web Development',
            description: 'Learn how browsers render accessible, responsive websites and how client-side interfaces communicate with web servers and APIs.',
            topics: ['Semantic HTML and accessible forms', 'CSS layout and responsive design', 'JavaScript and DOM events', 'HTTP, JSON, and API requests', 'Client-server application structure']
        },
        'data-science': {
            code: 'CSUC201',
            title: 'Fundamentals of Data Science',
            description: 'Explore how data is collected, cleaned, summarized, visualized, and used to support evidence-based decisions and predictive models.',
            topics: ['Data types and data quality', 'Descriptive statistics and probability', 'Data cleaning and exploration', 'Visualization and communicating findings', 'Introductory machine learning and evaluation']
        },
        networks: {
            code: 'ITUC201',
            title: 'Fundamentals of Computer Networks',
            description: 'Understand how devices exchange information across local and global networks, from physical links through addressing, routing, and application protocols.',
            topics: ['OSI and TCP/IP models', 'Ethernet, switching, and wireless LANs', 'IPv4 addressing and subnetting', 'Routing, DNS, and transport protocols', 'Network security and troubleshooting']
        }
    };

    document.querySelectorAll('.course-detail-button').forEach((button) => {
        button.addEventListener('click', () => {
            const course = courseDetails[button.dataset.course];
            if (!course) return;
            code.textContent = course.code;
            title.textContent = course.title;
            description.textContent = course.description;
            topics.replaceChildren(...course.topics.map((topic) => {
                const item = document.createElement('li');
                item.textContent = topic;
                return item;
            }));
            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');
            modalPanel.focus();
        });
    });

    closeButtons.forEach((button) => button.addEventListener('click', closeCourse));
    modal.addEventListener('click', (event) => {
        if (event.target === modal) closeCourse();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('show')) closeCourse();
    });
});
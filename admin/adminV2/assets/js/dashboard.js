(function () {
    const body = document.body;
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const toggleButton = document.querySelector('[data-toggle-sidebar]');
    const sectionLinks = Array.from(document.querySelectorAll('[data-section-target]'));
    const sections = Array.from(document.querySelectorAll('[data-section]'));

    function closeSidebar() {
        body.classList.remove('sidebar-open');
    }

    function openSidebar() {
        body.classList.add('sidebar-open');
    }

    function activateSection(targetId) {
        if (!targetId) return;
        sections.forEach((section) => {
            section.classList.toggle('is-active', section.id === targetId);
        });
        sectionLinks.forEach((link) => {
            const linkTarget = link.getAttribute('data-section-target');
            link.classList.toggle('is-active', linkTarget === targetId);
        });
    }

    function initNavigation() {
        sectionLinks.forEach((link) => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                const targetId = link.getAttribute('data-section-target');
                activateSection(targetId);
                closeSidebar();
                const headerOffset = 72;
                const targetSection = document.getElementById(targetId);
                if (targetSection) {
                    const top = targetSection.getBoundingClientRect().top + window.scrollY - headerOffset;
                    window.scrollTo({ top, behavior: 'smooth' });
                }
            });
        });

        const hash = window.location.hash.replace('#', '');
        if (hash) {
            activateSection(hash);
        }
    }

    function initSidebar() {
        if (toggleButton) {
            toggleButton.addEventListener('click', () => {
                if (body.classList.contains('sidebar-open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            });
        }

        if (overlay) {
            overlay.addEventListener('click', closeSidebar);
        }

        const prefersDesktop = window.matchMedia('(min-width: 1081px)');
        prefersDesktop.addEventListener('change', (event) => {
            if (event.matches) {
                closeSidebar();
            }
        });
    }

    function createOption(value, label, selectedValue) {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        option.selected = selectedValue === value;
        return option;
    }

    function initCourseForm() {
        const dashboardData = window.dashboardData || {};
        const courseForm = document.getElementById('course-form');
        const courseCategory = document.getElementById('course-category');
        const courseSubcategory = document.getElementById('course-subcategory');
        const courseMode = document.getElementById('course-mode');
        const courseId = document.getElementById('course-id');
        const courseCurrentCover = document.getElementById('course-current-cover');
        const courseTitle = document.getElementById('course-title');
        const courseHeadline = document.getElementById('course-headline');
        const courseCoverImage = document.getElementById('course-cover-image');
        const coursePrice = document.getElementById('course-price');
        const courseInstallments = document.getElementById('course-installments');
        const courseOverview = document.getElementById('course-overview');
        const courseGeneralObjectives = document.getElementById('course-general-objectives');
        const courseSpecificObjectives = document.getElementById('course-specific-objectives');
        const courseContents = document.getElementById('course-contents');
        const courseDetails = document.getElementById('course-details');
        const coursePdf = document.getElementById('course-pdf');
        const courseSubmit = document.getElementById('course-submit');
        const courseReset = document.getElementById('course-reset');
        const courseFormTitle = document.getElementById('course-form-title');
        const courseFormHelper = document.getElementById('course-form-helper');

        if (!courseForm || !courseCategory || !courseSubcategory) {
            return;
        }

        const categories = dashboardData.categories || [];
        const subcategories = dashboardData.subcategories || [];

        function renderSubcategories(selectedCategory, selectedSubcategory) {
            courseSubcategory.innerHTML = '';
            courseSubcategory.appendChild(createOption('', 'Selecciona uma subcategoria', ''));
            subcategories
                .filter((sub) => sub.category_id === selectedCategory)
                .forEach((sub) => {
                    courseSubcategory.appendChild(
                        createOption(sub.id || '', sub.name || 'Sem nome', selectedSubcategory)
                    );
                });
            courseSubcategory.disabled = courseSubcategory.options.length <= 1;
        }

        courseCategory.addEventListener('change', (event) => {
            renderSubcategories(event.target.value, '');
        });

        function resetCourseForm() {
            courseForm.reset();
            courseMode.value = 'create';
            courseId.value = '';
            courseCurrentCover.value = '';
            renderSubcategories(courseCategory.value, '');
            courseSubmit.textContent = 'Guardar curso';
            courseFormTitle.textContent = 'Adicionar curso';
            courseFormHelper.textContent = 'Preenche os campos para adicionar um novo curso ao catálogo.';
        }

        if (courseReset) {
            courseReset.addEventListener('click', resetCourseForm);
        }

        document.querySelectorAll('[data-course]').forEach((button) => {
            button.addEventListener('click', () => {
                let payload = {};
                try {
                    payload = JSON.parse(button.getAttribute('data-course') || '{}');
                } catch (error) {
                    payload = {};
                }

                courseMode.value = 'update';
                courseId.value = payload.id || '';
                courseCurrentCover.value = payload.cover_image || '';
                courseCategory.value = payload.category_id || '';
                renderSubcategories(payload.category_id || '', payload.subcategory_id || '');
                courseTitle.value = payload.title || '';
                courseHeadline.value = payload.headline || '';
                courseCoverImage.value = payload.cover_image || '';
                coursePrice.value = payload.price || '';
                courseInstallments.value = payload.installments || '1';
                courseOverview.value = payload.overview || '';
                courseGeneralObjectives.value = payload.general_objectives || '';
                courseSpecificObjectives.value = payload.specific_objectives || '';
                courseContents.value = payload.contents || '';
                courseDetails.value = payload.details || '';
                coursePdf.value = payload.pdf_url || '';

                courseSubmit.textContent = 'Actualizar curso';
                courseFormTitle.textContent = 'Editar curso';
                courseFormHelper.textContent = 'Actualiza os detalhes do curso seleccionado.';
                activateSection('courses');
                window.scrollTo({ top: courseForm.offsetTop - 80, behavior: 'smooth' });
            });
        });

        renderSubcategories(courseCategory.value, courseSubcategory.value);
    }

    initNavigation();
    initSidebar();
    initCourseForm();
})();

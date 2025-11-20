(function () {
    const navLinks = Array.from(document.querySelectorAll('[data-section-target]'));

    function setActiveLink(targetId) {
        navLinks.forEach((link) => {
            link.classList.toggle('active', link.getAttribute('data-section-target') === targetId);
        });
    }

    navLinks.forEach((link) => {
        link.addEventListener('click', (event) => {
            const targetId = link.getAttribute('data-section-target');
            const section = document.getElementById(targetId);
            if (section) {
                event.preventDefault();
                section.scrollIntoView({ behavior: 'smooth', block: 'start' });
                setActiveLink(targetId);
                history.replaceState(null, '', `#${targetId}`);
            }
        });
    });

    const initialHash = window.location.hash.replace('#', '');
    if (initialHash) {
        setActiveLink(initialHash);
    }

    const dashboardData = window.dashboardData || { subcategories: [] };
    const courseCategorySelect = document.getElementById('course-category');
    const courseSubcategorySelect = document.getElementById('course-subcategory');
    const courseForm = document.getElementById('course-form');
    const courseMode = document.getElementById('course-mode');
    const courseId = document.getElementById('course-id');
    const courseCurrentCover = document.getElementById('course-current-cover');
    const courseTitle = document.getElementById('course-title');
    const courseHeadline = document.getElementById('course-headline');
    const coursePrice = document.getElementById('course-price');
    const courseInstallments = document.getElementById('course-installments');
    const courseOverview = document.getElementById('course-overview');
    const courseGeneralObjectives = document.getElementById('course-general-objectives');
    const courseSpecificObjectives = document.getElementById('course-specific-objectives');
    const courseContents = document.getElementById('course-contents');
    const courseDetails = document.getElementById('course-details');
    const coursePdf = document.getElementById('course-pdf');
    const courseCoverInput = document.getElementById('course-cover-image');
    const courseFormTitle = document.getElementById('course-form-title');
    const courseFormHelper = document.getElementById('course-form-helper');
    const courseSubmit = document.getElementById('course-submit');
    const courseReset = document.getElementById('course-reset');

    function populateSubcategories(categoryId, selectedId) {
        if (!courseSubcategorySelect) {
            return;
        }
        courseSubcategorySelect.innerHTML = '<option value="">Selecciona uma subcategoria</option>';
        if (!categoryId) {
            return;
        }

        dashboardData.subcategories
            .filter((item) => String(item.category_id) === String(categoryId))
            .forEach((item) => {
                const option = document.createElement('option');
                option.value = item.id || '';
                option.textContent = item.name || '';
                if (selectedId && String(selectedId) === String(item.id)) {
                    option.selected = true;
                }
                courseSubcategorySelect.appendChild(option);
            });
    }

    function resetCourseForm() {
        if (!courseForm) {
            return;
        }
        courseForm.reset();
        if (courseMode) courseMode.value = 'create';
        if (courseId) courseId.value = '';
        if (courseCurrentCover) courseCurrentCover.value = '';
        populateSubcategories('', '');
        if (courseFormTitle) courseFormTitle.textContent = 'Adicionar curso';
        if (courseFormHelper) courseFormHelper.textContent = 'Preenche os campos para adicionar um novo curso ao catálogo.';
        if (courseSubmit) courseSubmit.textContent = 'Guardar curso';
    }

    function loadCourseIntoForm(courseData) {
        if (!courseData || !courseForm) {
            return;
        }
        if (courseMode) courseMode.value = 'update';
        if (courseId) courseId.value = courseData.id || '';
        if (courseCurrentCover) courseCurrentCover.value = courseData.cover_image || '';
        if (courseCategorySelect) courseCategorySelect.value = courseData.category_id || '';
        populateSubcategories(courseData.category_id || '', courseData.subcategory_id || '');
        if (courseTitle) courseTitle.value = courseData.title || '';
        if (courseHeadline) courseHeadline.value = courseData.headline || '';
        if (coursePrice) coursePrice.value = courseData.price || '';
        if (courseInstallments) courseInstallments.value = courseData.installments || '1';
        if (courseOverview) courseOverview.value = courseData.overview || '';
        if (courseGeneralObjectives) courseGeneralObjectives.value = courseData.general_objectives || '';
        if (courseSpecificObjectives) courseSpecificObjectives.value = courseData.specific_objectives || '';
        if (courseContents) courseContents.value = courseData.contents || '';
        if (courseDetails) courseDetails.value = courseData.details || '';
        if (coursePdf) coursePdf.value = courseData.pdf_url || '';
        if (courseCoverInput) courseCoverInput.value = courseData.cover_image || '';
        if (courseFormTitle) courseFormTitle.textContent = 'Editar curso';
        if (courseFormHelper) courseFormHelper.textContent = 'Actualiza as informações e guarda para publicar as alterações.';
        if (courseSubmit) courseSubmit.textContent = 'Guardar alterações';
        window.scrollTo({ top: courseForm.offsetTop - 80, behavior: 'smooth' });
    }

    if (courseCategorySelect) {
        courseCategorySelect.addEventListener('change', (event) => {
            populateSubcategories(event.target.value, '');
        });
    }

    document.querySelectorAll('[data-course]').forEach((button) => {
        button.addEventListener('click', () => {
            const payload = button.getAttribute('data-course');
            if (!payload) return;
            try {
                const courseData = JSON.parse(payload);
                loadCourseIntoForm(courseData);
            } catch (error) {
                console.error('Não foi possível carregar os dados do curso.', error);
            }
        });
    });

    if (courseReset) {
        courseReset.addEventListener('click', resetCourseForm);
    }

    // inicializa estado do formulário
    resetCourseForm();
})();

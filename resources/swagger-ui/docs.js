// Starts Swagger UI with the description of the API (the address is in the attribute "data-spec")
window.addEventListener('load', function () {
    var element = document.getElementById('swagger-ui');

    window.ui = SwaggerUIBundle({
        url: element.getAttribute('data-spec'),
        dom_id: '#swagger-ui',
        deepLinking: true,
        // The key must not stay in the browser, the user pastes it again after a reload
        persistAuthorization: false,
        docExpansion: 'list',
        tryItOutEnabled: true
    });
});

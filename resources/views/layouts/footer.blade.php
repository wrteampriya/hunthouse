<!----- THIS FORM USE FOR DELETE  ---->
<form method="GET" id="form-del">
    {{ csrf_field() }}

</form>
<!----- THIS FORM USE FOR DELETE  ---->

<footer class="footer mt-3">
    <div class="container-fluid">
        <div class="foot_text text-end">
            ©
            <script>
                document.write(new Date().getFullYear())
            </script> |{{ config('app.name') }}
        </div>

    </div>
</footer>

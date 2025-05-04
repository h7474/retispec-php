# Use the official Nginx image
FROM nginx:stable-alpine

# Remove the default Nginx configuration file
RUN rm /etc/nginx/conf.d/default.conf

# Copy the custom configuration file
COPY ./default.conf /etc/nginx/conf.d/default.conf

# Copy the frontend files to be served by Nginx
# COPY ../frontend /usr/share/nginx/html

# Expose port 80
EXPOSE 80

# Start Nginx
CMD ["nginx", "-g", "daemon off;"]

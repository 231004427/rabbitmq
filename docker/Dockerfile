FROM rabbitmq:3.7-management

MAINTAINER frognew

RUN ln -sf /usr/share/zoneinfo/Asia/Shanghai /etc/localtime && \
    echo "Asia/Shanghai" > /etc/timezone
    

ADD plugins/*.ez /opt/rabbitmq/plugins/
RUN rabbitmq-plugins enable --offline autocluster